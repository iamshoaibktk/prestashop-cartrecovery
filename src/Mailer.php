<?php
/**
 * Abandoned Cart Recovery: builds and sends the reminder e-mails.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Cartrecovery;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mailer
{
    const FONT = 'font-family:Tahoma,Arial,sans-serif;';

    /** @var \Cartrecovery */
    private $module;

    public function __construct(\Cartrecovery $module)
    {
        $this->module = $module;
    }

    /* =====================================================================
     *  Settings
     * =================================================================== */

    /**
     * Everything that shapes an e-mail, in one language. With $posted, values typed in the back
     * office form win over the saved ones (preview, test, one-off reminder), so nothing has to be saved first.
     * Multilingual fields may be posted as KEY[id_lang] (settings form) or KEY (one-off form).
     */
    public function settings($idLang, $posted = false, $step = 1)
    {
        $idLang = (int) $idLang;
        $step = max(1, min(\Cartrecovery::MAX_STEPS, (int) $step));
        $idDefault = (int) \Configuration::get('PS_LANG_DEFAULT');
        $defaults = null;
        $text = function ($key) use ($idLang, $idDefault, $posted, $step, &$defaults) {
            $oneOff = 'CR_ONEOFF_' . substr($key, 8);
            if (in_array($key, \Cartrecovery::STEP_TEXT_KEYS, true)) {
                $key .= '_' . $step;
            }
            if ($posted && \Tools::getIsset($oneOff)) {
                return (string) \Tools::getValue($oneOff);
            }
            if ($posted && \Tools::getIsset($key)) {
                $v = \Tools::getValue($key);
                if (is_array($v)) {
                    if (isset($v[$idLang])) {
                        return (string) $v[$idLang];
                    }
                } else {
                    return (string) $v;
                }
            }
            $saved = \Configuration::get($key, $idLang);
            if ($saved === false || $saved === null || $saved === '') {
                if ($key === 'CARTREC_TAGLINE') {
                    return '';
                }
                $saved = \Configuration::get($key, $idDefault);
            }
            if ($saved === false || $saved === null || $saved === '') {
                if ($defaults === null) {
                    $defaults = $this->module->getDefaultTexts($this->module->localeOf($idLang));
                }
                $saved = isset($defaults[$key]) ? $defaults[$key] : '';
            }

            return (string) $saved;
        };
        $get = function ($key) use ($posted) {
            $saved = \Configuration::get($key);

            return ($posted && \Tools::getIsset($key)) ? \Tools::getValue($key) : $saved;
        };

        $tpl = (string) $get('CARTREC_TPL');
        if (!isset(\Cartrecovery::$templates[$tpl])) {
            $tpl = 'classic';
        }
        $disc = [];
        if ($posted) {
            foreach (['on' => 'CARTREC_DISC', 'type' => 'CARTREC_DISC_TYPE', 'value' => 'CARTREC_DISC_VALUE', 'days' => 'CARTREC_DISC_DAYS',
                'min' => 'CARTREC_DISC_MIN', 'prefix' => 'CARTREC_DISC_PREFIX', 'apply' => 'CARTREC_DISC_APPLY'] as $k => $key) {
                if (\Tools::getIsset($key)) {
                    $v = \Tools::getValue($key);
                    $disc[$k] = in_array($k, ['value', 'min'], true) ? str_replace(',', '.', (string) $v) : $v;
                }
            }
        }

        $discount = Discount::settings($disc);
        $stepDisc = 'CARTREC_STEP' . $step . '_DISC';
        $useDisc = ($posted && \Tools::getIsset('CR_ONEOFF_DISC')) ? \Tools::getValue('CR_ONEOFF_DISC')
            : (($posted && \Tools::getIsset($stepDisc)) ? \Tools::getValue($stepDisc) : \Configuration::get($stepDisc));
        if (!(int) $useDisc) {
            $discount['on'] = 0;
        }

        return [
            'id_lang' => $idLang,
            'step' => $step,
            'subject' => $text('CARTREC_SUBJECT'),
            'title' => $text('CARTREC_TITLE'),
            'body' => $text('CARTREC_BODY'),
            'button' => trim($text('CARTREC_BTN')) ?: $this->module->t('Complete my order', [], 'Emails', $this->module->localeOf($idLang)),
            'footer' => trim($text('CARTREC_FOOTER')),
            'reas_text' => $text('CARTREC_REAS_TEXT'),
            'tagline' => trim($text('CARTREC_TAGLINE')),
            'tpl' => $tpl,
            'accent' => self::color($get('CARTREC_ACCENT'), \Cartrecovery::$templates[$tpl]['accent']),
            'header' => self::color($get('CARTREC_HEADER'), \Cartrecovery::$templates[$tpl]['header']),
            'reas' => (int) $get('CARTREC_REAS'),
            'hero' => $this->getHeroUrl(),
            'discount' => $discount,
        ];
    }

    public static function color($value, $fallback)
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    /** Absolute URL of the banner image (uploaded one, or the bundled default). */
    public function getHeroUrl()
    {
        $file = basename((string) \Configuration::get('CARTREC_HERO'));
        if ($file === '' || !is_file($this->module->getLocalPath() . 'img/' . $file)) {
            $file = 'hero-default.jpg';
        }

        return \Context::getContext()->link->getMediaLink($this->module->getPathUri() . 'img/' . $file);
    }

    /* =====================================================================
     *  Building
     * =================================================================== */

    public function getCartUrl(\Cart $cart, $idLang)
    {
        return \Context::getContext()->link->getPageLink('cart', true, (int) $idLang, http_build_query([
            'action' => 'show',
            'recover_cart' => (int) $cart->id,
            'token_cart' => md5(_COOKIE_KEY_ . 'recover_cart_' . (int) $cart->id),
        ]), false, (int) $cart->id_shop);
    }

    /**
     * @param array $data from Repository::loadCartData()
     * @param array $st from settings()
     * @param bool $isTest test/preview: links open the shop without logging anyone in or unsubscribing anyone
     * @param array|null $discount from Discount::forEmail()
     *
     * @return array subject, title, html (body only), txt, preheader, url, unsub_url, settings
     */
    public function buildEmail(array $data, array $st, $isTest = false, $discount = null, $idReminder = 0)
    {
        $link = \Context::getContext()->link;
        $customer = $data['customer'];
        $idLang = (int) $data['id_lang'];
        $locale = $data['locale'];
        $idShop = (int) $data['cart']->id_shop;
        $tracked = !$isTest && $idReminder && (int) \Configuration::get('CARTREC_TRACK');
        if ($isTest) {
            $url = $link->getPageLink('cart', true, $idLang, 'action=show');
        } elseif ($tracked) {
            // Counts the click, then opens the cart (same one-click recovery link).
            $url = $this->module->getTrackUrl($idReminder, 'c', $idLang, $idShop);
        } else {
            $url = $this->getCartUrl($data['cart'], $idLang);
        }
        $pixel = $tracked
            ? '<img src="' . htmlspecialchars($this->module->getTrackUrl($idReminder, 'o', $idLang, $idShop), ENT_QUOTES, 'UTF-8') . '" width="1" height="1" alt="" style="display:block;width:1px;height:1px;border:0;">'
            : '';
        $unsubUrl = $isTest
            ? $link->getPageLink('index', true, $idLang)
            : $this->module->getUnsubscribeUrl((int) $customer->id, $idLang, (int) $data['cart']->id_shop);
        $shopName = (string) \Configuration::get('PS_SHOP_NAME');
        $esc = function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        };
        $disc = $discount ?: ['code' => '', 'value' => '', 'expiry' => ''];

        $htmlVars = [
            '{firstname}' => $esc($customer->firstname),
            '{lastname}' => $esc($customer->lastname),
            '{cart_total}' => $esc($data['total_display']),
            '{cart_url}' => $esc($url),
            '{shop_name}' => $esc($shopName),
            '{discount_code}' => $esc($disc['code']),
            '{discount_value}' => $esc($disc['value']),
            '{discount_expiry}' => $esc($disc['expiry']),
            '{unsubscribe_url}' => $esc($unsubUrl),
            '{cart_products}' => $this->renderProductsHtml($data, $st),
            '{discount_block}' => $discount ? $this->renderDiscountHtml($discount, $st, $locale) : '',
            '{cart_link}' => $this->renderButtonHtml($url, $st, $locale),
        ];

        $body = $this->prepareBody((string) $st['body'], (bool) $discount);
        // Block placeholders alone in a paragraph replace the whole paragraph (valid HTML, no empty gaps).
        foreach (['{cart_products}', '{cart_link}', '{discount_block}'] as $block) {
            $body = preg_replace('#<p[^>]*>\s*(?:&nbsp;|\s)*' . preg_quote($block, '#') . '(?:&nbsp;|\s)*</p>#i', $block, $body);
        }
        $body = preg_replace('#<p(?=[\s>])(?![^>]*style=)#i', '<p style="margin:0 0 14px;"', $body);
        $html = strtr($body, $htmlVars);

        $tr = function ($s, $p = []) use ($locale) {
            return $this->module->t($s, $p, 'Emails', $locale);
        };
        $txtDiscount = '';
        if ($discount) {
            $txtDiscount = $tr('Your personal discount code') . ': ' . $disc['code'] . "\n"
                . $this->discountLine($discount, $st, $locale);
        }
        $txtVars = [
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{cart_total}' => $data['total_display'],
            '{cart_url}' => $url,
            '{shop_name}' => $shopName,
            '{discount_code}' => $disc['code'],
            '{discount_value}' => $disc['value'],
            '{discount_expiry}' => $disc['expiry'],
            '{unsubscribe_url}' => $unsubUrl,
            '{cart_products}' => $this->renderProductsTxt($data, $locale),
            '{discount_block}' => $txtDiscount,
            '{cart_link}' => $st['button'] . ': ' . $url,
        ];
        $txt = preg_replace(['#<br\s*/?>#i', '#</p>#i', '#</li>#i'], ["\n", "\n\n", "\n"], $this->prepareBody((string) $st['body'], (bool) $discount));
        $txt = html_entity_decode(strip_tags(strtr($txt, $txtVars)), ENT_QUOTES, 'UTF-8');
        $txt = trim(preg_replace("#\n{3,}#", "\n\n", $txt));

        $plainVars = [
            '{firstname}' => $customer->firstname,
            '{lastname}' => $customer->lastname,
            '{shop_name}' => $shopName,
            '{cart_total}' => $data['total_display'],
            '{discount_value}' => $disc['value'],
            '{discount_code}' => $disc['code'],
        ];

        return [
            'subject' => trim(strtr((string) $st['subject'], $plainVars)),
            'title' => trim(strtr((string) $st['title'], $plainVars)),
            'html' => $html,
            'txt' => $txt,
            'url' => $url,
            'unsub_url' => $unsubUrl,
            'preheader' => $tr('%firstname%, your items are still in your cart.', ['%firstname%' => $customer->firstname]),
            'locale' => $locale,
            'id_lang' => $idLang,
            'pixel' => $pixel,
            'settings' => $st,
        ];
    }

    /** Makes sure the cart link is always there, and the discount when one is offered. */
    protected function prepareBody($body, $hasDiscount)
    {
        if (strpos($body, '{cart_link}') === false && strpos($body, '{cart_url}') === false) {
            $body .= '<p>{cart_link}</p>';
        }
        if ($hasDiscount && strpos($body, '{discount_block}') === false && strpos($body, '{discount_code}') === false) {
            $pos = strpos($body, '{cart_link}');
            $body = $pos !== false
                ? substr($body, 0, $pos) . '{discount_block}' . substr($body, $pos)
                : $body . '<p>{discount_block}</p>';
        }

        return $body;
    }

    protected function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /** Black or white text, whichever reads best on $hex. */
    public static function contrastColor($hex)
    {
        $h = ltrim((string) $hex, '#');
        if (strlen($h) !== 6) {
            return '#ffffff';
        }
        $l = 0.299 * hexdec(substr($h, 0, 2)) + 0.587 * hexdec(substr($h, 2, 2)) + 0.114 * hexdec(substr($h, 4, 2));

        return $l > 150 ? '#1f1f1f' : '#ffffff';
    }

    protected function discountLine(array $discount, array $st, $locale)
    {
        $d = $st['discount'];
        $line = $d['type'] === 'shipping'
            ? $this->module->t('Free shipping on your order', [], 'Emails', $locale)
            : $this->module->t('%value% off your order', ['%value%' => $discount['value']], 'Emails', $locale);

        return $line . ' · ' . $this->module->t('Valid until %date%', ['%date%' => $discount['expiry']], 'Emails', $locale);
    }

    protected function renderDiscountHtml(array $discount, array $st, $locale)
    {
        $f = self::FONT;
        $acc = $st['accent'];
        $note = $st['discount']['apply']
            ? $this->module->t('It is already applied to your cart.', [], 'Emails', $locale)
            : $this->module->t('Enter it at checkout.', [], 'Emails', $locale);
        $border = $st['tpl'] === 'minimal' ? '1px solid #1f1f1f' : '2px dashed ' . $acc;
        $bg = $st['tpl'] === 'bold' ? '#faf3ee' : '#fafafa';

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 18px;"><tr>'
            . '<td align="center" bgcolor="' . $bg . '" style="background:' . $bg . ';border:' . $border . ';padding:18px 16px;' . $f . '">'
            . '<div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#5f5f5f;">' . $this->e($this->module->t('Your personal discount code', [], 'Emails', $locale)) . '</div>'
            . '<div style="font-size:26px;font-weight:bold;letter-spacing:2px;color:#1f1f1f;margin:6px 0;font-family:\'Courier New\',Courier,monospace;">' . $this->e($discount['code']) . '</div>'
            . '<div style="font-size:14px;color:#2a2a2a;">' . $this->e($this->discountLine($discount, $st, $locale)) . '</div>'
            . '<div style="font-size:12px;color:#5f5f5f;margin-top:4px;">' . $this->e($note) . '</div>'
            . '</td></tr></table>';
    }

    protected function renderProductsHtml(array $data, array $st)
    {
        $f = self::FONT;
        $tpl = $st['tpl'];
        $acc = $st['accent'];
        $locale = $data['locale'];
        $total = $this->e($data['total_display']);
        $totalLabel = $this->e($this->module->t('Total (tax incl.)', [], 'Emails', $locale));
        $rows = [];
        foreach ($data['products'] as $p) {
            $rows[] = [
                'name' => $this->e($p['name']),
                'meta' => ($p['attributes'] !== '' ? $this->e($p['attributes']) . ' · ' : '')
                    . $this->e($this->module->t('Quantity: %qty%', ['%qty%' => (int) $p['qty']], 'Emails', $locale)),
                'price' => $this->e($p['total_display']),
                'img' => $p['img'] ? $this->e($p['img']) : '',
            ];
        }
        $img = function ($r, $size, $extra = '') {
            return $r['img']
                ? '<img src="' . $r['img'] . '" width="' . $size . '" alt="' . $r['name'] . '" style="display:block;width:' . $size . 'px;max-width:' . $size . 'px;height:auto;border:0;' . $extra . '">'
                : '';
        };

        if ($tpl === 'showcase') {
            // Two-column grid (stacks on mobile).
            $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">';
            foreach (array_chunk($rows, 2) as $pair) {
                $h .= '<tr>';
                foreach ($pair as $i => $r) {
                    $h .= '<td class="cr-col" width="50%" valign="top" style="width:50%;padding:0 ' . ($i === 0 ? '8px' : '0') . ' 16px ' . ($i === 1 ? '8px' : '0') . ';">'
                        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e3e3;">'
                        . '<tr><td align="center" bgcolor="#fafafa" style="background:#fafafa;padding:12px;">' . $img($r, 150, 'margin:0 auto;') . '</td></tr>'
                        . '<tr><td style="padding:12px 14px;' . $f . '">'
                        . '<div style="font-size:14px;font-weight:bold;color:#1f1f1f;line-height:1.35;">' . $r['name'] . '</div>'
                        . '<div style="font-size:12px;color:#5f5f5f;margin-top:4px;">' . $r['meta'] . '</div>'
                        . '<div style="font-size:16px;font-weight:bold;color:#1f1f1f;margin-top:8px;">' . $r['price'] . '</div>'
                        . '</td></tr></table></td>';
                }
                if (count($pair) === 1) {
                    $h .= '<td class="cr-col cr-hide-m" width="50%" style="width:50%;padding:0 0 16px 8px;">&nbsp;</td>';
                }
                $h .= '</tr>';
            }
            $h .= '</table>'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 6px;"><tr><td bgcolor="#1f1f1f" style="background:#1f1f1f;padding:0;">'
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td style="padding:14px 16px;color:#ffffff;font-size:15px;' . $f . '">' . $totalLabel . '</td>'
                . '<td align="right" style="padding:14px 16px;color:#ffffff;font-size:20px;font-weight:bold;white-space:nowrap;' . $f . '">' . $total . '</td>'
                . '</tr></table></td></tr></table>';

            return $h;
        }

        if ($tpl === 'bold') {
            $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 18px;">';
            foreach ($rows as $r) {
                $h .= '<tr><td style="padding:0 0 10px;">'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#faf3ee" style="background:#faf3ee;border-radius:8px;"><tr>'
                    . '<td width="96" valign="middle" style="padding:12px 0 12px 16px;width:80px;">' . $img($r, 80, 'background:#ffffff;border-radius:6px;') . '</td>'
                    . '<td valign="middle" style="padding:12px 12px 12px 16px;' . $f . '">'
                    . '<div style="font-size:15px;font-weight:bold;color:#1f1f1f;line-height:1.35;">' . $r['name'] . '</div>'
                    . '<div style="font-size:13px;color:#5f5f5f;margin-top:3px;">' . $r['meta'] . '</div>'
                    . '<div class="cr-pm" style="display:none;mso-hide:all;font-size:15px;font-weight:bold;color:#1f1f1f;margin-top:6px;">' . $r['price'] . '</div>'
                    . '</td>'
                    . '<td class="cr-pcol" align="right" valign="middle" style="padding:12px 16px 12px 0;white-space:nowrap;font-size:16px;font-weight:bold;color:#1f1f1f;' . $f . '">' . $r['price'] . '</td>'
                    . '</tr></table></td></tr>';
            }
            $h .= '<tr><td><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td style="padding:8px 16px 0;font-size:15px;color:#3f3f3f;' . $f . '">' . $totalLabel . '</td>'
                . '<td align="right" style="padding:8px 16px 0;font-size:20px;font-weight:bold;color:' . $acc . ';white-space:nowrap;' . $f . '">' . $total . '</td>'
                . '</tr></table></td></tr></table>';

            return $h;
        }

        if ($tpl === 'minimal') {
            $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #1f1f1f;margin:8px 0 18px;">';
            foreach ($rows as $r) {
                $h .= '<tr>'
                    . '<td width="82" valign="middle" style="padding:16px 0;border-bottom:1px solid #e3e3e3;width:64px;">' . $img($r, 64) . '</td>'
                    . '<td valign="middle" style="padding:16px 12px 16px 18px;border-bottom:1px solid #e3e3e3;' . $f . '">'
                    . '<div style="font-size:15px;color:#1f1f1f;line-height:1.35;">' . $r['name'] . '</div>'
                    . '<div style="font-size:13px;color:#6b6b6b;margin-top:3px;">' . $r['meta'] . '</div>'
                    . '<div class="cr-pm" style="display:none;mso-hide:all;font-size:15px;color:#1f1f1f;margin-top:6px;">' . $r['price'] . '</div>'
                    . '</td>'
                    . '<td class="cr-pcol" align="right" valign="middle" style="padding:16px 0;border-bottom:1px solid #e3e3e3;white-space:nowrap;font-size:15px;color:#1f1f1f;' . $f . '">' . $r['price'] . '</td>'
                    . '</tr>';
            }
            $h .= '<tr><td colspan="3" style="padding:0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td style="padding:16px 0;font-size:15px;color:#6b6b6b;' . $f . '">' . $totalLabel . '</td>'
                . '<td align="right" style="padding:16px 0;font-size:15px;font-weight:bold;color:#1f1f1f;white-space:nowrap;' . $f . '">' . $total . '</td>'
                . '</tr></table></td></tr></table>';

            return $h;
        }

        // classic
        $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e3e3e3;border-collapse:collapse;margin:8px 0 18px;">';
        foreach ($rows as $r) {
            $h .= '<tr>'
                . '<td width="92" valign="middle" style="padding:14px 0 14px 16px;border-bottom:1px solid #e3e3e3;width:76px;">' . $img($r, 76) . '</td>'
                . '<td valign="middle" style="padding:14px 12px 14px 16px;border-bottom:1px solid #e3e3e3;' . $f . '">'
                . '<div style="font-size:15px;font-weight:bold;color:#1f1f1f;line-height:1.35;">' . $r['name'] . '</div>'
                . '<div style="font-size:13px;color:#5f5f5f;margin-top:3px;line-height:1.4;">' . $r['meta'] . '</div>'
                . '<div class="cr-pm" style="display:none;mso-hide:all;font-size:14px;font-weight:bold;color:#1f1f1f;margin-top:6px;">' . $r['price'] . '</div>'
                . '</td>'
                . '<td class="cr-pcol" align="right" valign="middle" style="padding:14px 16px 14px 0;border-bottom:1px solid #e3e3e3;white-space:nowrap;font-size:15px;font-weight:bold;color:#1f1f1f;' . $f . '">' . $r['price'] . '</td>'
                . '</tr>';
        }
        $h .= '<tr><td colspan="3" style="padding:0;background:#fafafa;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td style="padding:14px 16px;font-size:15px;color:#3f3f3f;' . $f . '">' . $totalLabel . '</td>'
            . '<td align="right" style="padding:14px 16px;white-space:nowrap;font-size:18px;font-weight:bold;color:#1f1f1f;' . $f . '">' . $total . '</td>'
            . '</tr></table></td></tr></table>';

        return $h;
    }

    protected function renderProductsTxt(array $data, $locale)
    {
        $lines = [];
        foreach ($data['products'] as $p) {
            $lines[] = '- ' . $p['name'] . ($p['attributes'] !== '' ? ' (' . $p['attributes'] . ')' : '') . ' x' . (int) $p['qty'] . ': ' . $p['total_display'];
        }
        $lines[] = $this->module->t('Total (tax incl.)', [], 'Emails', $locale) . ': ' . $data['total_display'];

        return implode("\n", $lines);
    }

    protected function renderButtonHtml($url, array $st, $locale)
    {
        $u = $this->e($url);
        $label = $this->e($st['button']);
        $acc = $st['accent'];
        $txt = self::contrastColor($acc);
        $f = self::FONT;
        $fallback = '<p style="margin:12px 0 22px;text-align:%s;font-size:12px;color:#5f5f5f;' . $f . '">'
            . $this->e($this->module->t('Button not working?', [], 'Emails', $locale)) . ' '
            . '<a href="' . $u . '" target="_blank" style="color:#1f1f1f;">' . $this->e($this->module->t('Open my cart', [], 'Emails', $locale)) . '</a></p>';

        switch ($st['tpl']) {
            case 'bold':
                return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:26px auto 0;"><tr>'
                    . '<td align="center" bgcolor="' . $acc . '" style="border-radius:999px;background:' . $acc . ';">'
                    . '<a href="' . $u . '" target="_blank" style="display:inline-block;padding:17px 42px;' . $f . 'font-size:16px;font-weight:bold;color:' . $txt . ';text-decoration:none;border-radius:999px;">' . $label . '</a>'
                    . '</td></tr></table>' . sprintf($fallback, 'center');
            case 'minimal':
                return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 0;"><tr>'
                    . '<td style="border:2px solid ' . $acc . ';">'
                    . '<a href="' . $u . '" target="_blank" style="display:inline-block;padding:14px 32px;' . $f . 'font-size:15px;font-weight:bold;letter-spacing:.5px;color:' . $acc . ';text-decoration:none;">' . $label . ' &rarr;</a>'
                    . '</td></tr></table>' . sprintf($fallback, 'left');
            case 'showcase':
                return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0 0;"><tr>'
                    . '<td align="center" bgcolor="' . $acc . '" style="background:' . $acc . ';">'
                    . '<a href="' . $u . '" target="_blank" style="display:block;padding:19px 12px;' . $f . 'font-size:17px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:' . $txt . ';text-decoration:none;">' . $label . '</a>'
                    . '</td></tr></table>' . sprintf($fallback, 'center');
            default:
                return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:22px auto 0;"><tr>'
                    . '<td align="center" bgcolor="' . $acc . '" style="border-radius:4px;background:' . $acc . ';">'
                    . '<a href="' . $u . '" target="_blank" style="display:inline-block;padding:17px 38px;' . $f . 'font-size:16px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:' . $txt . ';text-decoration:none;border-radius:4px;">' . $label . '</a>'
                    . '</td></tr></table>' . sprintf($fallback, 'center');
        }
    }

    /** @return array [[title, subtitle], ...] from "Title | subtitle" lines, max 3 */
    public static function parseReassurance($text)
    {
        $items = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $items[] = [$parts[0], isset($parts[1]) ? $parts[1] : ''];
            if (count($items) === 3) {
                break;
            }
        }

        return $items;
    }

    /** Reassurance strip (3 short promises), styled per design. */
    protected function renderReassuranceHtml(array $st)
    {
        $items = self::parseReassurance($st['reas_text']);
        if (!$st['reas'] || !$items) {
            return '';
        }
        $f = self::FONT;
        $acc = $st['accent'];
        $width = (int) floor(100 / count($items)) . '%';

        if ($st['tpl'] === 'minimal') {
            $titles = [];
            foreach ($items as $it) {
                $titles[] = $this->e($it[0]);
            }

            return '<div style="' . $f . 'font-size:12px;color:#1f1f1f;margin-bottom:8px;">' . implode(' · ', $titles) . '</div>';
        }

        $cells = '';
        $last = count($items) - 1;
        foreach ($items as $i => $it) {
            $title = '<div style="font-size:13px;font-weight:bold;color:#1f1f1f;">' . $this->e($it[0]) . '</div>'
                . ($it[1] !== '' ? '<div style="font-size:11px;color:#5f5f5f;margin-top:3px;">' . $this->e($it[1]) . '</div>' : '');
            if ($st['tpl'] === 'bold') {
                $cells .= '<td class="cr-reas-td" width="' . $width . '" valign="top" style="padding:0 6px;' . $f . '">'
                    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td valign="top" style="padding:5px 8px 0 0;"><div style="width:8px;height:8px;background:' . $acc . ';border-radius:2px;font-size:0;line-height:0;">&nbsp;</div></td>'
                    . '<td valign="top">' . $title . '</td></tr></table></td>';
            } elseif ($st['tpl'] === 'showcase') {
                $cells .= '<td class="cr-reas-td" width="' . $width . '" valign="top" style="padding:0 ' . ($i < $last ? '10px' : '0') . ' 0 0;' . $f . '">'
                    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                    . '<td align="center" style="border:1px solid #e3e3e3;border-top:3px solid ' . $acc . ';padding:12px 8px;">' . $title . '</td>'
                    . '</tr></table></td>';
            } else {
                $cells .= '<td class="cr-reas-td" width="' . $width . '" align="center" valign="top" style="padding:0 6px;' . $f . '">' . $title . '</td>';
            }
        }
        $table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>' . $cells . '</tr></table>';

        if ($st['tpl'] === 'bold') {
            return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td class="cr-pad" style="padding:0 32px 28px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#faf3ee" style="background:#faf3ee;border-radius:8px;"><tr><td style="padding:16px 12px;">'
                . $table . '</td></tr></table></td></tr></table>';
        }
        if ($st['tpl'] === 'showcase') {
            return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="cr-pad" style="padding:0 32px 28px;">' . $table . '</td></tr></table>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td class="cr-pad" style="padding:20px 32px;border-top:1px solid #e3e3e3;">' . $table . '</td></tr></table>';
    }

    /** Template variables passed to Mail::send() (shared with the preview). */
    public function getTemplateVars(array $mail)
    {
        $st = $mail['settings'];
        $tagline = $st['tagline'];

        return [
            '{cr_lang}' => $this->e(substr((string) $mail['locale'], 0, 2)),
            '{cr_pixel_tag}' => isset($mail['pixel']) ? $mail['pixel'] : '',
            '{cr_title}' => $this->e($mail['title']),
            '{cr_title_txt}' => $mail['title'],
            '{cr_preheader}' => $this->e($mail['preheader']),
            '{cr_body}' => $mail['html'],
            '{cr_body_txt}' => $mail['txt'],
            '{cr_accent}' => $st['accent'],
            '{cr_accent_text}' => self::contrastColor($st['accent']),
            '{cr_header_bg}' => $st['header'],
            '{cr_header_text}' => self::contrastColor($st['header']),
            '{cr_hero}' => $this->e($st['hero']),
            '{cr_reassurance}' => $this->renderReassuranceHtml($st),
            '{cr_footer}' => nl2br($this->e($st['footer'])),
            '{cr_footer_txt}' => $st['footer'],
            '{cr_tagline}' => $this->e($tagline),
            '{cr_tagline_sep}' => $tagline !== '' ? ' · ' . $this->e($tagline) : '',
            '{cr_unsub_url}' => $this->e($mail['unsub_url']),
            '{cr_unsub_url_txt}' => $mail['unsub_url'],
            '{cr_unsub_label_txt}' => $this->module->t('Unsubscribe from cart reminders', [], 'Emails', $mail['locale']),
            '{cr_tagline_sep_txt}' => $tagline !== '' ? ' · ' . $tagline : '',
            '{cr_unsub_label}' => $this->e($this->module->t('Unsubscribe from cart reminders', [], 'Emails', $mail['locale'])),
            '{cr_shop_email}' => $this->e(\Configuration::get('PS_SHOP_EMAIL')),
            '{cr_shop_name_txt}' => (string) \Configuration::get('PS_SHOP_NAME'),
            '{cr_shop_domain}' => $this->e(\Configuration::get('PS_SHOP_DOMAIN')),
        ];
    }

    public function getMailTemplateName($tpl)
    {
        return 'cartrecovery_' . (isset(\Cartrecovery::$templates[$tpl]) ? $tpl : 'classic');
    }

    /** The e-mail as the customer would see it (for the back office preview). */
    public function renderPreviewPage(array $mail, $banner = true)
    {
        $context = \Context::getContext();
        $tpl = \Tools::file_get_contents($this->module->getLocalPath() . 'mails/en/' . $this->getMailTemplateName($mail['settings']['tpl']) . '.html');
        $vars = $this->getTemplateVars($mail) + [
            '{shop_name}' => $this->e(\Configuration::get('PS_SHOP_NAME')),
            '{shop_url}' => $context->link->getPageLink('index', true),
            '{shop_logo}' => $context->link->getMediaLink(_PS_IMG_ . (\Configuration::get('PS_LOGO_MAIL') ?: \Configuration::get('PS_LOGO'))),
        ];
        $html = strtr($tpl, $vars);
        if (!$banner) {
            return $html;
        }
        $bar = '<div style="background:#363a41;color:#fff;font:14px Arial,sans-serif;padding:10px 16px;text-align:center;">'
            . $this->e($this->module->t('Preview')) . ' · ' . $this->e($this->module->t('Subject')) . ': <strong>' . $this->e($mail['subject']) . '</strong>'
            . ' · ' . $this->e($this->module->t('In the preview, the link opens the cart page without logging anyone in.')) . '</div>';

        return preg_replace('#<body([^>]*)>#i', '<body$1>' . $bar, $html, 1);
    }

    /* =====================================================================
     *  Sending
     * =================================================================== */

    /** Step a manual or one-off reminder uses: the next one, or the last one again when all were sent. */
    public function nextManualStep($idCart)
    {
        $last = $this->module->repo()->lastStep($idCart);

        return max(1, min($last + 1, max($last, \Cartrecovery::maxStep())));
    }

    /**
     * Sends one reminder step for a cart and records it.
     *
     * @param string $mode auto|manual|custom
     * @param array|null $st settings to use instead of the saved ones (one-off reminder)
     * @param int|null $step null: the next step (manual sending)
     *
     * @return true|string true on success, error message otherwise
     */
    public function sendReminder($idCart, $mode = 'manual', $st = null, $step = null)
    {
        $m = $this->module;
        $repo = $m->repo();
        $data = $repo->loadCartData((int) $idCart);
        if (!$data) {
            return $m->t('Cart #%id%: not found or empty.', ['%id%' => (int) $idCart]);
        }
        $cart = $data['cart'];
        $customer = $data['customer'];
        if ($cart->orderExists() || $this->orderedSince($customer->id, $cart->date_upd)) {
            return $m->t('Cart #%id%: the customer has already ordered.', ['%id%' => (int) $idCart]);
        }
        if (!\Validate::isEmail($customer->email)) {
            return $m->t('Cart #%id%: the customer e-mail is not valid.', ['%id%' => (int) $idCart]);
        }
        if ($repo->isUnsubscribed($customer->email)) {
            return $m->t('Cart #%id%: the customer unsubscribed from cart reminders.', ['%id%' => (int) $idCart]);
        }
        $step = $step ? (int) $step : $this->nextManualStep($idCart);

        $info = [
            'id_customer' => (int) $customer->id,
            'id_shop' => (int) $cart->id_shop,
            'id_lang' => (int) $data['id_lang'],
            'email' => $customer->email,
        ];
        // The record exists before sending (tracking id); automatic sends claim it so two cron runs never e-mail twice.
        $rec = $repo->prepare($idCart, $step, $mode, $info);
        if (!$rec) {
            return $m->t('Cart #%id%: already reminded.', ['%id%' => (int) $idCart]);
        }

        $st = $st ?: $this->settings($data['id_lang'], false, $step);
        $previousRule = $repo->lastCartRule($idCart);
        $discount = $m->discount()->forEmail($data, $st, true);
        $mail = $this->buildEmail($data, $st, false, $discount, $rec['id']);
        $ok = $this->send($mail, $customer->email, trim($customer->firstname . ' ' . $customer->lastname), (int) $cart->id_shop);

        if (!$ok) {
            if ($discount && $discount['id_cart_rule'] && (int) $discount['id_cart_rule'] !== $previousRule) {
                $rule = new \CartRule((int) $discount['id_cart_rule']);
                if (\Validate::isLoadedObject($rule)) {
                    $rule->delete();
                }
            }
            if ($rec['new']) {
                $repo->release($rec['id']);
            }

            return $m->t('Cart #%id%: the e-mail could not be sent (check the e-mail settings of your shop).', ['%id%' => (int) $idCart]);
        }

        if ($discount) {
            $info['id_cart_rule'] = (int) $discount['id_cart_rule'];
            $info['discount_code'] = $discount['code'];
        }
        $repo->record($rec['id'], $rec['new'], $mode, $info);

        return true;
    }

    /** Has the customer placed any order since this date? */
    protected function orderedSince($idCustomer, $date)
    {
        return (bool) \Db::getInstance()->getValue('SELECT id_order FROM `' . _DB_PREFIX_ . 'orders`
            WHERE id_customer = ' . (int) $idCustomer . ' AND date_add >= \'' . pSQL($date) . '\'');
    }

    public function send(array $mail, $to, $toName, $idShop = null, $useBcc = true)
    {
        $bcc = $useBcc ? trim((string) \Configuration::get('CARTREC_BCC')) : '';
        // Real reminders only: a test e-mail has no customer to unsubscribe.
        \Cartrecovery::$sendingUnsubUrl = $useBcc ? $mail['unsub_url'] : null;
        try {
            $ok = (bool) \Mail::send(
                (int) $mail['id_lang'],
                $this->getMailTemplateName($mail['settings']['tpl']),
                $mail['subject'],
                $this->getTemplateVars($mail),
                $to,
                $toName,
                null,
                null,
                null,
                null,
                $this->module->getLocalPath() . 'mails/',
                false,
                $idShop,
                ($bcc !== '' && \Validate::isEmail($bcc)) ? $bcc : null
            );
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('[cartrecovery] mail: ' . $e->getMessage(), 3, null, null, null, true);
            $ok = false;
        }
        \Cartrecovery::$sendingUnsubUrl = null;

        return $ok;
    }

    /**
     * Test e-mail with a real cart as the example. Links never log anyone in, codes are not created.
     *
     * @return true|string
     */
    public function sendTest(array $st, $email, $idCart, $idLang)
    {
        $m = $this->module;
        if (!\Validate::isEmail($email)) {
            return $m->t('Enter a valid test e-mail address.');
        }
        $sample = $idCart ? $m->repo()->loadCartData((int) $idCart, (int) $idLang) : $this->getSampleCart($idLang);
        if (!$sample) {
            return $m->t('No cart is available to build the test e-mail.');
        }
        $mail = $this->buildEmail($sample, $st, true, $m->discount()->forEmail($sample, $st, false));
        $mail['subject'] = '[TEST] ' . $mail['subject'];

        return $this->send($mail, $email, '', (int) \Context::getContext()->shop->id, false)
            ? true
            : $m->t('The test e-mail could not be sent. Check Advanced parameters > E-mail.');
    }

    /** Most recent abandoned cart, in the given language (falls back to the latest cart with products). */
    public function getSampleCart($idLang)
    {
        $repo = $this->module->repo();
        foreach ($repo->getCartRows(365) as $r) {
            if ($data = $repo->loadCartData((int) $r['id_cart'], (int) $idLang)) {
                return $data;
            }
        }

        return null;
    }
}

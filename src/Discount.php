<?php
/**
 * Abandoned Cart Recovery: personal discount codes (one cart rule per reminder).
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Cartrecovery;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Discount
{
    const TYPES = ['percent', 'amount', 'shipping'];

    /** @var \Cartrecovery */
    private $module;

    public function __construct(\Cartrecovery $module)
    {
        $this->module = $module;
    }

    /** Discount settings, from the saved configuration or the given overrides. */
    public static function settings(array $override = [])
    {
        $type = isset($override['type']) ? $override['type'] : (string) \Configuration::get('CARTREC_DISC_TYPE');

        $st = [
            'on' => isset($override['on']) ? (int) $override['on'] : (int) \Configuration::get('CARTREC_DISC'),
            'type' => in_array($type, self::TYPES, true) ? $type : 'percent',
            'value' => isset($override['value']) ? (float) $override['value'] : (float) \Configuration::get('CARTREC_DISC_VALUE'),
            'days' => max(1, isset($override['days']) ? (int) $override['days'] : (int) \Configuration::get('CARTREC_DISC_DAYS')),
            'min' => max(0, isset($override['min']) ? (float) $override['min'] : (float) \Configuration::get('CARTREC_DISC_MIN')),
            'prefix' => isset($override['prefix']) ? $override['prefix'] : (string) \Configuration::get('CARTREC_DISC_PREFIX'),
            'apply' => isset($override['apply']) ? (int) $override['apply'] : (int) \Configuration::get('CARTREC_DISC_APPLY'),
        ];
        $st['prefix'] = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $st['prefix']));
        if ($st['type'] !== 'shipping' && $st['value'] <= 0) {
            $st['on'] = 0;
        }
        if ($st['type'] === 'percent') {
            $st['value'] = min(100, $st['value']);
        }

        return $st;
    }

    /**
     * Discount values for an e-mail. With $create, a real cart rule is created for the customer
     * (or the code of an earlier, still valid reminder of the same cart is reused).
     *
     * @return array|null code, value, expiry, id_cart_rule; null when discounts are off
     */
    public function forEmail(array $data, array $st, $create)
    {
        $st = $st['discount'];
        if (!$st['on']) {
            return null;
        }
        $idLang = (int) $data['id_lang'];
        $expiry = time() + $st['days'] * 86400;
        $out = [
            'value' => $this->describe($st, $data['currency'], $idLang),
            'expiry' => $this->formatDate($expiry, $idLang),
            'code' => ($st['prefix'] !== '' ? $st['prefix'] . '-' : '') . 'PREVIEW',
            'id_cart_rule' => 0,
        ];
        if (!$create) {
            return $out;
        }

        $cart = $data['cart'];
        // One code per cart: later reminders repeat the code of an earlier one while it is still valid.
        $idRule = $this->module->repo()->lastCartRule((int) $cart->id);
        if ($idRule) {
            $rule = new \CartRule($idRule);
            if (\Validate::isLoadedObject($rule) && $rule->active && (int) $rule->quantity > 0 && strtotime($rule->date_to) > time()) {
                $out['code'] = $rule->code;
                $out['expiry'] = $this->formatDate(strtotime($rule->date_to), $idLang);
                $out['id_cart_rule'] = (int) $rule->id;

                return $out;
            }
        }

        $rule = $this->create($cart, $data['customer'], $st, $expiry);
        if (!$rule) {
            return null;
        }
        $out['code'] = $rule->code;
        $out['id_cart_rule'] = (int) $rule->id;

        return $out;
    }

    /** "10 %", "5,00 €" or "Free shipping" in the customer's language. */
    public function describe(array $st, $currency, $idLang)
    {
        $locale = $this->module->localeOf($idLang);
        if ($st['type'] === 'shipping') {
            return $this->module->t('free shipping', [], 'Emails', $locale);
        }
        if ($st['type'] === 'amount') {
            return $this->module->repo()->formatPrice($st['value'], $currency, $idLang);
        }

        return rtrim(rtrim(number_format($st['value'], 2, '.', ''), '0'), '.') . ' %';
    }

    public function formatDate($timestamp, $idLang)
    {
        $lang = new \Language((int) $idLang);
        $format = (\Validate::isLoadedObject($lang) && $lang->date_format_lite) ? $lang->date_format_lite : 'Y-m-d';

        return date($format, (int) $timestamp);
    }

    /** @return \CartRule|null */
    protected function create(\Cart $cart, \Customer $customer, array $st, $expiry)
    {
        $rule = new \CartRule();
        $rule->code = $this->uniqueCode($st['prefix']);
        $rule->id_customer = (int) $customer->id;
        $rule->date_from = date('Y-m-d H:i:s', time() - 60);
        $rule->date_to = date('Y-m-d H:i:s', $expiry);
        $rule->quantity = 1;
        $rule->quantity_per_user = 1;
        $rule->partial_use = 0;
        $rule->highlight = 0;
        $rule->active = 1;
        $rule->priority = 1;
        $rule->cart_rule_restriction = 0;
        $rule->minimum_amount = $st['min'];
        $rule->minimum_amount_currency = (int) $cart->id_currency;
        $rule->minimum_amount_tax = 1;
        $rule->minimum_amount_shipping = 0;
        $rule->reduction_currency = (int) $cart->id_currency;
        $rule->reduction_tax = 1;
        if ($st['type'] === 'shipping') {
            $rule->free_shipping = 1;
        } elseif ($st['type'] === 'amount') {
            $rule->reduction_amount = $st['value'];
        } else {
            $rule->reduction_percent = $st['value'];
        }
        $rule->description = 'Abandoned Cart Recovery: cart #' . (int) $cart->id;
        $names = [];
        foreach (\Language::getLanguages(false) as $lang) {
            $locale = !empty($lang['locale']) ? $lang['locale'] : $lang['language_code'];
            $names[(int) $lang['id_lang']] = \Tools::substr($this->module->t('Your cart discount', [], 'Emails', $locale), 0, 254);
        }
        $rule->name = $names;

        try {
            if (!$rule->add()) {
                return null;
            }
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('[cartrecovery] discount: ' . $e->getMessage(), 3, null, 'Cart', (int) $cart->id, true);

            return null;
        }

        if ($st['apply']) {
            // Already in the cart when the customer comes back; PrestaShop removes it if it no longer applies.
            try {
                $cart->addCartRule((int) $rule->id);
            } catch (\Throwable $e) {
            }
        }

        return $rule;
    }

    protected function uniqueCode($prefix)
    {
        // No 0/O, 1/I/L: easy to read and type.
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $random = '';
            for ($i = 0; $i < 8; ++$i) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = ($prefix !== '' ? $prefix . '-' : '') . $random;
        } while (\CartRule::cartRuleExists($code));

        return $code;
    }

    /**
     * Deletes our expired codes that were never used (one day after they expire).
     *
     * @return int number of codes deleted
     */
    public function cleanup()
    {
        $p = _DB_PREFIX_;
        $ids = \Db::getInstance()->executeS('
            SELECT DISTINCT r.id_cart_rule FROM `' . $p . \Cartrecovery::TABLE . '` r
            INNER JOIN `' . $p . 'cart_rule` cr ON cr.id_cart_rule = r.id_cart_rule
            WHERE r.id_cart_rule > 0 AND cr.date_to < \'' . pSQL(date('Y-m-d H:i:s', time() - 86400)) . '\'
              AND NOT EXISTS (SELECT 1 FROM `' . $p . 'order_cart_rule` ocr WHERE ocr.id_cart_rule = r.id_cart_rule)
            LIMIT 200');
        $n = 0;
        foreach ((array) $ids as $row) {
            $rule = new \CartRule((int) $row['id_cart_rule']);
            if (\Validate::isLoadedObject($rule) && $rule->delete()) {
                ++$n;
            }
        }

        return $n;
    }
}

<?php
/**
 * Abandoned Cart Recovery: back office page (settings, appearance, carts, one-off reminder).
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Cartrecovery;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Admin
{
    const PER_PAGE = 20;
    const TABS = ['settings', 'design', 'carts', 'compose'];

    /** @var \Cartrecovery */
    private $module;

    /** @var \Context */
    private $context;

    public function __construct(\Cartrecovery $module)
    {
        $this->module = $module;
        $this->context = \Context::getContext();
    }

    private function t($s, array $p = [])
    {
        return $this->module->t($s, $p);
    }

    private function langId()
    {
        $id = (int) \Tools::getValue('cr_lang');
        foreach (\Language::getLanguages(false) as $l) {
            if ((int) $l['id_lang'] === $id) {
                return $id;
            }
        }

        return (int) $this->context->language->id;
    }

    public function handle()
    {
        $m = $this->module;
        $notice = '';
        $tab = in_array(\Tools::getValue('cr_tab'), self::TABS, true) ? \Tools::getValue('cr_tab') : 'settings';
        $mailer = $m->mailer();

        // Preview: the e-mail built from the values currently in the form (nothing is saved).
        if (\Tools::isSubmit('submitCrPreview')) {
            $idLang = $this->langId();
            $idCart = (int) \Tools::getValue('cr_cart');
            $sample = $idCart ? $m->repo()->loadCartData($idCart, $idLang) : $mailer->getSampleCart($idLang);
            if ($sample) {
                $step = $idCart ? $mailer->nextManualStep($idCart) : max(1, (int) \Tools::getValue('cr_step'));
                $st = $mailer->settings($sample['id_lang'], true, $step);
                $mail = $mailer->buildEmail($sample, $st, true, $m->discount()->forEmail($sample, $st, false));
                header('Content-Type: text/html; charset=utf-8');
                exit($mailer->renderPreviewPage($mail, !\Tools::getValue('cr_live')));
            }
            if (\Tools::getValue('cr_live')) {
                header('Content-Type: text/html; charset=utf-8');
                exit('<p style="font:14px Arial,sans-serif;padding:24px;color:#5b7178">' . htmlspecialchars($this->t('No abandoned cart yet: the preview needs one cart with products from a customer or guest.')) . '</p>');
            }
            $notice .= $m->displayWarning($this->t('No abandoned cart yet: the preview needs one cart with products from a customer or guest.'));
        }

        if (\Tools::isSubmit('submitCrSettings')) {
            $notice .= $this->saveSettings();
        }
        if (\Tools::isSubmit('submitCrTest')) {
            $idLang = $this->langId();
            $res = $mailer->sendTest($mailer->settings($idLang, true, max(1, (int) \Tools::getValue('cr_step'))), trim((string) \Tools::getValue('CARTREC_TEST_EMAIL')), 0, $idLang);
            $notice .= $res === true ? $m->displayConfirmation($this->t('Test e-mail sent.')) : $m->displayError(htmlspecialchars($res));
        }
        if (\Tools::isSubmit('submitCrDesign')) {
            $tab = 'design';
            $notice .= $this->saveDesign();
        }

        if (\Tools::isSubmit('submitCrComposeTest') || \Tools::isSubmit('submitCrCompose')) {
            $tab = 'compose';
            $idCart = (int) \Tools::getValue('cr_cart');
            $data = $idCart ? $m->repo()->loadCartData($idCart) : null;
            if (!$data) {
                $notice .= $m->displayError($this->t('Choose a cart.'));
            } else {
                $step = $mailer->nextManualStep($idCart);
                $st = $mailer->settings($data['id_lang'], true, $step);
                if (trim($st['subject']) === '' || trim(strip_tags($st['body'])) === '') {
                    $notice .= $m->displayError($this->t('The subject and the message are required.'));
                } elseif (\Tools::isSubmit('submitCrComposeTest')) {
                    $res = $mailer->sendTest($st, trim((string) \Tools::getValue('CARTREC_TEST_EMAIL')), $idCart, $data['id_lang']);
                    $notice .= $res === true ? $m->displayConfirmation($this->t('Test e-mail sent.')) : $m->displayError(htmlspecialchars($res));
                } else {
                    $res = $mailer->sendReminder($idCart, 'custom', $st, $step);
                    if ($res === true) {
                        \Tools::redirectAdmin($this->url(['cr_tab' => 'carts', 'cr_status' => 'all', 'cr_done' => 1]));
                    }
                    $notice .= $m->displayError(htmlspecialchars($res));
                }
            }
        }

        if (\Tools::getValue('cr_send') || \Tools::isSubmit('submitCrBulk')) {
            $tab = 'carts';
            $ids = \Tools::getValue('cr_send')
                ? [(int) \Tools::getValue('cr_send')]
                : array_map('intval', (array) \Tools::getValue('cr_carts', []));
            if (!$ids) {
                $notice .= $m->displayWarning($this->t('No cart selected.'));
            } else {
                $sent = 0;
                $errors = [];
                foreach (array_unique(array_filter($ids)) as $id) {
                    $res = $mailer->sendReminder($id, 'manual');
                    if ($res === true) {
                        ++$sent;
                    } else {
                        $errors[] = $res;
                    }
                }
                if (!$errors) {
                    // Redirect so that refreshing the page never sends the e-mails again.
                    $back = ['cr_tab' => 'carts', 'cr_done' => $sent];
                    foreach (['cr_status', 'cr_days', 'cr_q', 'cr_page'] as $k) {
                        if (\Tools::getValue($k) !== false && \Tools::getValue($k) !== '') {
                            $back[$k] = \Tools::getValue($k);
                        }
                    }
                    \Tools::redirectAdmin($this->url($back));
                }
                if ($sent) {
                    $notice .= $m->displayConfirmation($this->t('Reminders sent: %n%', ['%n%' => $sent]));
                }
                $notice .= $m->displayError(implode('<br>', array_map('htmlspecialchars', $errors)));
            }
        }

        if ((int) \Tools::getValue('cr_done') > 0) {
            $tab = 'carts';
            $notice .= $m->displayConfirmation($this->t('Reminders sent: %n%', ['%n%' => (int) \Tools::getValue('cr_done')]));
        }

        $controller = $this->context->controller;
        $controller->addCSS($m->getPathUri() . 'views/css/admin.css');
        $controller->addJS($m->getPathUri() . 'views/js/admin.js');
        if ($tab === 'settings' || $tab === 'compose') {
            $controller->addJS(_PS_JS_DIR_ . 'tiny_mce/tiny_mce.js');
            $controller->addJS(_PS_JS_DIR_ . 'admin/tinymce.inc.js');
        }

        return $m->renderAuthor() . $notice . $this->render($tab);
    }

    /** Absolute link to this configuration page (works with the PS 9 Symfony module route too). */
    public function url(array $params = [])
    {
        $url = $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->module->name]);
        if (strpos($url, 'configure=') === false && strpos($url, '/configure/' . $this->module->name) === false) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'configure=' . $this->module->name;
        }
        foreach ($params as $k => $v) {
            $url .= '&' . $k . '=' . urlencode((string) $v);
        }

        return $url;
    }

    /** Splits a URL into form action + hidden fields (for GET forms). */
    private function splitUrl($url)
    {
        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str(html_entity_decode($parts['query']), $query);
        }
        $action = (isset($parts['scheme']) ? $parts['scheme'] . '://' : '')
            . (isset($parts['host']) ? $parts['host'] : '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (isset($parts['path']) ? $parts['path'] : '');

        return ['action' => $action, 'fields' => $query];
    }

    /* =====================================================================
     *  Saving
     * =================================================================== */

    /** Posted KEY[id_lang] values, cleaned. */
    private function postedLang($key, $html = false)
    {
        $out = [];
        $v = \Tools::getValue($key);
        foreach (\Language::getLanguages(false) as $l) {
            $id = (int) $l['id_lang'];
            $val = (is_array($v) && isset($v[$id])) ? (string) $v[$id] : (string) \Configuration::get($key, $id);
            $out[$id] = $html ? $val : trim(strip_tags($val));
        }

        return $out;
    }

    private function saveSettings()
    {
        $m = $this->module;
        $errors = [];
        $bcc = trim((string) \Tools::getValue('CARTREC_BCC'));
        $test = trim((string) \Tools::getValue('CARTREC_TEST_EMAIL'));
        $idDefault = (int) \Configuration::get('PS_LANG_DEFAULT');
        $delays = [];
        for ($i = 1; $i <= \Cartrecovery::MAX_STEPS; ++$i) {
            $delays[$i] = max(1, (int) \Tools::getValue('CARTREC_STEP' . $i . '_DELAY'));
        }
        $on2 = (int) (bool) \Tools::getValue('CARTREC_STEP2_ON');
        $on3 = $on2 && (int) (bool) \Tools::getValue('CARTREC_STEP3_ON');

        $texts = [];
        for ($i = 1; $i <= \Cartrecovery::MAX_STEPS; ++$i) {
            foreach (\Cartrecovery::STEP_TEXT_KEYS as $k) {
                $texts[$k . '_' . $i] = $this->postedLang($k . '_' . $i, $k === 'CARTREC_BODY');
            }
        }
        for ($i = 1; $i <= ($on3 ? 3 : ($on2 ? 2 : 1)); ++$i) {
            if (trim($texts['CARTREC_SUBJECT_' . $i][$idDefault]) === '' || trim(strip_tags($texts['CARTREC_BODY_' . $i][$idDefault])) === '') {
                $errors[] = $this->t('Reminder %n%: the subject and the message are required in the default language.', ['%n%' => $i]);
            }
            if ($i > 1 && $delays[$i] <= $delays[$i - 1]) {
                $errors[] = $this->t('Reminder %n% must be sent later than reminder %p%.', ['%n%' => $i, '%p%' => $i - 1]);
            }
        }
        if ($bcc !== '' && !\Validate::isEmail($bcc)) {
            $errors[] = $this->t('The blind copy address is not valid.');
        }
        if ($test !== '' && !\Validate::isEmail($test)) {
            $errors[] = $this->t('The test address is not valid.');
        }
        $type = (string) \Tools::getValue('CARTREC_DISC_TYPE');
        if (!in_array($type, Discount::TYPES, true)) {
            $type = 'percent';
        }
        $value = (float) str_replace(',', '.', (string) \Tools::getValue('CARTREC_DISC_VALUE'));
        if ((int) \Tools::getValue('CARTREC_DISC') && $type !== 'shipping' && ($value <= 0 || ($type === 'percent' && $value > 100))) {
            $errors[] = $this->t('Enter a discount value above 0 (100 at most for a percentage).');
        }
        if ($errors) {
            return $m->displayError(implode('<br>', $errors));
        }

        \Configuration::updateValue('CARTREC_AUTO', (int) (bool) \Tools::getValue('CARTREC_AUTO'));
        for ($i = 1; $i <= \Cartrecovery::MAX_STEPS; ++$i) {
            \Configuration::updateValue('CARTREC_STEP' . $i . '_DELAY', $delays[$i]);
            \Configuration::updateValue('CARTREC_STEP' . $i . '_DISC', (int) (bool) \Tools::getValue('CARTREC_STEP' . $i . '_DISC'));
        }
        \Configuration::updateValue('CARTREC_STEP2_ON', $on2);
        \Configuration::updateValue('CARTREC_STEP3_ON', (int) $on3);
        \Configuration::updateValue('CARTREC_MAXAGE', max(1, (int) \Tools::getValue('CARTREC_MAXAGE')));
        \Configuration::updateValue('CARTREC_MINAMOUNT', max(0, (float) str_replace(',', '.', (string) \Tools::getValue('CARTREC_MINAMOUNT'))));
        $audience = (string) \Tools::getValue('CARTREC_AUDIENCE');
        \Configuration::updateValue('CARTREC_AUDIENCE', in_array($audience, ['all', 'newsletter', 'optin'], true) ? $audience : 'all');
        \Configuration::updateValue('CARTREC_TRACK', (int) (bool) \Tools::getValue('CARTREC_TRACK'));
        \Configuration::updateValue('CARTREC_BCC', $bcc);
        \Configuration::updateValue('CARTREC_TEST_EMAIL', $test);
        \Configuration::updateValue('CARTREC_DISC', (int) (bool) \Tools::getValue('CARTREC_DISC'));
        \Configuration::updateValue('CARTREC_DISC_TYPE', $type);
        \Configuration::updateValue('CARTREC_DISC_VALUE', max(0, $type === 'percent' ? min(100, $value) : $value));
        \Configuration::updateValue('CARTREC_DISC_DAYS', max(1, (int) \Tools::getValue('CARTREC_DISC_DAYS')));
        \Configuration::updateValue('CARTREC_DISC_MIN', max(0, (float) str_replace(',', '.', (string) \Tools::getValue('CARTREC_DISC_MIN'))));
        \Configuration::updateValue('CARTREC_DISC_PREFIX', strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) \Tools::getValue('CARTREC_DISC_PREFIX'))));
        \Configuration::updateValue('CARTREC_DISC_APPLY', (int) (bool) \Tools::getValue('CARTREC_DISC_APPLY'));
        foreach ($texts as $key => $byLang) {
            // Trusted admin content: HTML allowed in the message.
            \Configuration::updateValue($key, $byLang, strpos($key, 'CARTREC_BODY') === 0);
        }

        return $m->displayConfirmation($this->t('Settings saved.'));
    }

    private function saveDesign()
    {
        $m = $this->module;
        $tpl = (string) \Tools::getValue('CARTREC_TPL');
        if (!isset(\Cartrecovery::$templates[$tpl])) {
            return $m->displayError($this->t('Unknown design.'));
        }
        $out = '';
        \Configuration::updateValue('CARTREC_TPL', $tpl);
        \Configuration::updateValue('CARTREC_ACCENT', Mailer::color(\Tools::getValue('CARTREC_ACCENT'), \Cartrecovery::$templates[$tpl]['accent']));
        \Configuration::updateValue('CARTREC_HEADER', Mailer::color(\Tools::getValue('CARTREC_HEADER'), \Cartrecovery::$templates[$tpl]['header']));
        \Configuration::updateValue('CARTREC_REAS', (int) (bool) \Tools::getValue('CARTREC_REAS'));
        foreach (\Cartrecovery::SHARED_TEXT_KEYS as $key) {
            \Configuration::updateValue($key, $this->postedLang($key));
        }

        $dir = $m->getLocalPath() . 'img/';
        $current = basename((string) \Configuration::get('CARTREC_HERO'));
        if (\Tools::getValue('CARTREC_HERO_RM') && $current) {
            @unlink($dir . $current);
            \Configuration::updateValue('CARTREC_HERO', '');
            $current = '';
        }
        if (!empty($_FILES['CARTREC_HERO']['tmp_name'])) {
            if ($error = \ImageManager::validateUpload($_FILES['CARTREC_HERO'], 4000000)) {
                $out .= $m->displayError($error);
            } else {
                $ext = strtolower(pathinfo($_FILES['CARTREC_HERO']['name'], PATHINFO_EXTENSION));
                $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : 'jpg';
                $name = 'hero_' . time() . '.' . $ext;
                if (!@move_uploaded_file($_FILES['CARTREC_HERO']['tmp_name'], $dir . $name)) {
                    $out .= $m->displayError($this->t('The image could not be saved (check the permissions of the module img/ folder).'));
                } else {
                    if ($current) {
                        @unlink($dir . $current);
                    }
                    \Configuration::updateValue('CARTREC_HERO', $name);
                }
            }
        }

        return $out . $m->displayConfirmation($this->t('Appearance saved.'));
    }

    /* =====================================================================
     *  Page
     * =================================================================== */

    private function render($tab)
    {
        $m = $this->module;
        $repo = $m->repo();
        $mailer = $m->mailer();
        $languages = [];
        foreach (\Language::getLanguages(false) as $l) {
            $languages[] = ['id' => (int) $l['id_lang'], 'iso' => strtoupper($l['iso_code']), 'name' => $l['name']];
        }
        $idLang = (int) $this->context->language->id;
        $filters = [
            'status' => in_array(\Tools::getValue('cr_status'), ['all', 'none', 'sent', 'won', 'unsub'], true) ? \Tools::getValue('cr_status') : 'none',
            'days' => in_array((int) \Tools::getValue('cr_days'), [7, 30, 90], true) ? (int) \Tools::getValue('cr_days') : 30,
            'q' => trim((string) \Tools::getValue('cr_q')),
            'page' => max(1, (int) \Tools::getValue('cr_page')),
        ];
        $maxStep = \Cartrecovery::maxStep();

        $stats = $repo->getStats(30);
        $carts = [];
        $total = 0;
        $pages = 1;
        if ($tab === 'carts') {
            $all = $repo->getCartRows($filters['days'], $filters['q']);
            if ($filters['status'] !== 'all') {
                $all = array_values(array_filter($all, function ($r) use ($filters) {
                    if ($filters['status'] === 'unsub') {
                        return (bool) $r['unsubscribed'];
                    }

                    return $r['status'] === $filters['status'] && !($filters['status'] === 'none' && $r['unsubscribed']);
                }));
            }
            $total = count($all);
            $pages = max(1, (int) ceil($total / self::PER_PAGE));
            $filters['page'] = min($filters['page'], $pages);
            foreach (array_slice($all, ($filters['page'] - 1) * self::PER_PAGE, self::PER_PAGE) as $row) {
                $row = array_merge($row, $repo->getCartSummary((int) $row['id_cart']));
                $next = min($row['last_step'] + 1, max($row['last_step'], $maxStep));
                $row['next_step'] = max(1, $next);
                $row['is_resend'] = $row['last_step'] >= $maxStep;
                $row['send_url'] = $this->url(['cr_tab' => 'carts', 'cr_send' => $row['id_cart']] + $this->filterParams($filters));
                $row['compose_url'] = $this->url(['cr_tab' => 'compose', 'cr_cart' => $row['id_cart']]);
                $row['cart_admin_url'] = $this->context->link->getAdminLink('AdminCarts', true, [], ['id_cart' => (int) $row['id_cart'], 'viewcart' => 1]);
                $row['order_admin_url'] = $row['id_order']
                    ? $this->context->link->getAdminLink('AdminOrders', true, ['orderId' => (int) $row['id_order']], ['id_order' => (int) $row['id_order'], 'vieworder' => 1])
                    : '';
                $row['date_display'] = \Tools::displayDate($row['date_upd'], true);
                $row['sent_display'] = $row['date_sent'] ? \Tools::displayDate($row['date_sent'], false) : '';
                $row['lang_iso'] = strtoupper((string) \Language::getIsoById((int) $row['id_lang']));
                $carts[] = $row;
            }
        }

        $compose = ['carts' => [], 'selected' => 0, 'look' => null, 'step' => 1];
        if ($tab === 'compose') {
            $wanted = (int) \Tools::getValue('cr_cart');
            foreach ($repo->getCartRows(90) as $r) {
                if ($r['status'] === 'won' || $r['unsubscribed']) {
                    continue;
                }
                $sum = $repo->getCartSummary((int) $r['id_cart']);
                if (!$sum['count']) {
                    continue;
                }
                $compose['carts'][] = [
                    'id_cart' => (int) $r['id_cart'],
                    'name' => trim($r['firstname'] . ' ' . $r['lastname']),
                    'firstname' => $r['firstname'],
                    'email' => $r['email'],
                    'total' => $sum['total_display'],
                    'count' => $sum['count'],
                    'img' => $sum['product_img'],
                    'lang' => strtoupper((string) \Language::getIsoById((int) $r['id_lang'])),
                    'id_lang' => (int) $r['id_lang'],
                    'date' => \Tools::displayDate($r['date_upd'], true),
                    'status' => $r['last_step']
                        ? $this->t('Reminder %n% sent on %date%', ['%n%' => $r['last_step'], '%date%' => \Tools::displayDate($r['date_sent'], false)])
                        : $this->t('Not reminded'),
                    'sent' => $r['last_step'] ? 1 : 0,
                ];
            }
            $ids = array_column($compose['carts'], 'id_cart');
            $compose['selected'] = in_array($wanted, $ids, true) ? $wanted : (int) reset($ids);
            if ($compose['selected']) {
                $sel = $compose['carts'][array_search($compose['selected'], $ids, true)];
                $compose['step'] = $mailer->nextManualStep($compose['selected']);
                $compose['look'] = $mailer->settings($sel['id_lang'] ?: $idLang, \Tools::isSubmit('submitCrCompose') || \Tools::isSubmit('submitCrComposeTest'), $compose['step']);
            }
        }

        // Texts per step and language (settings tab) and shared texts per language (appearance tab).
        $texts = [];
        for ($i = 1; $i <= \Cartrecovery::MAX_STEPS; ++$i) {
            foreach (\Cartrecovery::STEP_TEXT_KEYS as $k) {
                foreach ($languages as $l) {
                    $texts[$i][strtolower(substr($k, 8))][$l['id']] = (string) \Configuration::get($k . '_' . $i, $l['id']);
                }
            }
        }
        $shared = [];
        foreach (\Cartrecovery::SHARED_TEXT_KEYS as $k) {
            foreach ($languages as $l) {
                $shared[strtolower(substr($k, 8))][$l['id']] = (string) \Configuration::get($k, $l['id']);
            }
        }

        $lastRun = json_decode((string) \Configuration::get('CARTREC_LASTRUN'), true);
        $disc = Discount::settings(['on' => (int) \Configuration::get('CARTREC_DISC')]);
        $sampleDisc = $m->discount()->describe($disc + ['value' => $disc['value']], $this->context->currency, $idLang);

        $this->context->smarty->assign([
            'cr' => [
                'tab' => $tab,
                'urls' => [
                    'settings' => $this->url(['cr_tab' => 'settings']),
                    'carts' => $this->url(['cr_tab' => 'carts']),
                    'design' => $this->url(['cr_tab' => 'design']),
                    'compose' => $this->url(['cr_tab' => 'compose']),
                    'page' => $this->url(['cr_tab' => 'carts'] + $this->filterParams($filters, false)),
                ],
                'logo' => $m->getPathUri() . 'logo.png',
                'stats' => $stats,
                'languages' => $languages,
                'id_lang' => $idLang,
                'id_lang_default' => (int) \Configuration::get('PS_LANG_DEFAULT'),
                'texts' => $texts,
                'shared' => $shared,
                'max_steps' => \Cartrecovery::MAX_STEPS,
                'cfg' => [
                    'auto' => (int) \Configuration::get('CARTREC_AUTO'),
                    'step_delay' => [1 => \Cartrecovery::stepDelay(1), 2 => \Cartrecovery::stepDelay(2), 3 => \Cartrecovery::stepDelay(3)],
                    'step_disc' => [1 => (int) \Configuration::get('CARTREC_STEP1_DISC'), 2 => (int) \Configuration::get('CARTREC_STEP2_DISC'), 3 => (int) \Configuration::get('CARTREC_STEP3_DISC')],
                    'step2' => (int) \Configuration::get('CARTREC_STEP2_ON'),
                    'step3' => (int) \Configuration::get('CARTREC_STEP3_ON'),
                    'maxage' => (int) \Configuration::get('CARTREC_MAXAGE'),
                    'minamount' => (float) \Configuration::get('CARTREC_MINAMOUNT'),
                    'audience' => (string) \Configuration::get('CARTREC_AUDIENCE'),
                    'track' => (int) \Configuration::get('CARTREC_TRACK'),
                    'bcc' => (string) \Configuration::get('CARTREC_BCC'),
                    'test_email' => (string) \Configuration::get('CARTREC_TEST_EMAIL'),
                    'disc' => (int) \Configuration::get('CARTREC_DISC'),
                    'disc_type' => $disc['type'],
                    'disc_value' => (float) \Configuration::get('CARTREC_DISC_VALUE'),
                    'disc_days' => $disc['days'],
                    'disc_min' => $disc['min'],
                    'disc_prefix' => $disc['prefix'],
                    'disc_apply' => $disc['apply'],
                    'disc_example' => $sampleDisc,
                ],
                'look' => $mailer->settings($idLang),
                'has_custom_hero' => (bool) \Configuration::get('CARTREC_HERO'),
                'templates' => $this->templateList(),
                'compose' => $compose,
                'cron_url' => $m->getCronUrl(),
                'last_run' => is_array($lastRun) ? $lastRun : null,
                'last_run_date' => is_array($lastRun) && !empty($lastRun['date']) ? \Tools::displayDate($lastRun['date'], true) : '',
                'unsub_count' => $repo->countUnsubscribed(),
                'filters' => $filters,
                'carts' => $carts,
                'total' => $total,
                'pages' => $pages,
                'max_step' => $maxStep,
                'vars' => [
                    '{firstname}' => $this->t('Customer first name'),
                    '{lastname}' => $this->t('Customer last name'),
                    '{cart_products}' => $this->t('Table of the cart products'),
                    '{cart_total}' => $this->t('Cart total, tax incl.'),
                    '{cart_link}' => $this->t('Button back to the cart'),
                    '{cart_url}' => $this->t('Plain link to the cart'),
                    '{shop_name}' => $this->t('Shop name'),
                    '{discount_block}' => $this->t('Box with the discount code'),
                    '{discount_code}' => $this->t('The discount code alone'),
                    '{discount_value}' => $this->t('Discount value (10 %, 5.00 or free shipping)'),
                    '{discount_expiry}' => $this->t('Last day the code works'),
                    '{unsubscribe_url}' => $this->t('Unsubscribe link (always in the footer too)'),
                ],
                'filter_form' => $this->splitUrl($this->url(['cr_tab' => 'carts'])),
                'iso' => $this->context->language->iso_code,
                'currency_sign' => $this->context->currency->sign,
                'multishop' => \Shop::isFeatureActive(),
                'shop_name' => $this->context->shop->name,
            ],
        ]);

        return $m->display($m->getLocalPath() . 'cartrecovery.php', 'views/templates/admin/configure.tpl');
    }

    private function templateList()
    {
        $t = [
            'classic' => [$this->t('Classic'), $this->t('Dark band with the logo centred, title in capitals.')],
            'bold' => [$this->t('Bold'), $this->t('Dark header, coloured accents, products as cards, round button.')],
            'minimal' => [$this->t('Minimal'), $this->t('White background, lots of space, outlined button.')],
            'showcase' => [$this->t('Showcase'), $this->t('Large banner photo, products in a grid, full-width button.')],
        ];
        $out = [];
        foreach (\Cartrecovery::$templates as $code => $c) {
            $out[$code] = $c + ['name' => $t[$code][0], 'desc' => $t[$code][1]];
        }

        return $out;
    }

    private function filterParams(array $filters, $withPage = true)
    {
        $p = ['cr_status' => $filters['status'], 'cr_days' => $filters['days']];
        if ($filters['q'] !== '') {
            $p['cr_q'] = $filters['q'];
        }
        if ($withPage) {
            $p['cr_page'] = $filters['page'];
        }

        return $p;
    }
}

<?php
/**
 * Abandoned Cart Recovery
 *
 * Reminds customers who left products in their cart, with a one-click link back to it,
 * an optional personal discount code and an unsubscribe link. Manual or automatic (cron).
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

spl_autoload_register(function ($class) {
    $prefix = 'Iamshoaibktk\\Cartrecovery\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Iamshoaibktk\Cartrecovery\Admin;
use Iamshoaibktk\Cartrecovery\Discount;
use Iamshoaibktk\Cartrecovery\Mailer;
use Iamshoaibktk\Cartrecovery\Repository;

class Cartrecovery extends Module
{
    const TABLE = 'cartrecovery_reminder';
    const TABLE_UNSUB = 'cartrecovery_unsubscribe';
    const CRON_BATCH = 50;
    const MAX_STEPS = 3;

    /** Multilingual text settings (one value per language). Subject, title and body exist per reminder step. */
    const STEP_TEXT_KEYS = ['CARTREC_SUBJECT', 'CARTREC_TITLE', 'CARTREC_BODY'];
    const SHARED_TEXT_KEYS = ['CARTREC_BTN', 'CARTREC_FOOTER', 'CARTREC_REAS_TEXT', 'CARTREC_TAGLINE'];

    /** E-mail designs and their default colours. */
    public static $templates = [
        'classic' => ['accent' => '#1f1f1f', 'header' => '#1f1f1f'],
        'bold' => ['accent' => '#e4572e', 'header' => '#16181d'],
        'minimal' => ['accent' => '#1f1f1f', 'header' => '#ffffff'],
        'showcase' => ['accent' => '#2f6fdf', 'header' => '#1f2430'],
    ];

    /** Set while one of our e-mails is being sent (adds the List-Unsubscribe header). */
    public static $sendingUnsubUrl = null;

    public function __construct()
    {
        $this->name = 'cartrecovery';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'iamshoaibktk@gmail.com';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Abandoned Cart Recovery', [], 'Modules.Cartrecovery.Admin');
        $this->description = $this->trans('Reminds customers about the cart they left, with a one-click link back to it, an optional discount code and an unsubscribe link. Manual or automatic.', [], 'Modules.Cartrecovery.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall this module?', [], 'Modules.Cartrecovery.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Public translation helper for the classes in src/.
     *
     * @param string $section Admin|Emails|Shop
     * @param string|null $locale e.g. de-DE, for e-mails in the customer's language
     */
    public function t($text, array $params = [], $section = 'Admin', $locale = null)
    {
        $out = $this->trans($text, $params, 'Modules.Cartrecovery.' . $section, $locale);
        if ($locale !== null && strpos((string) $locale, 'en') !== 0 && $out === strtr($text, $params)) {
            // PrestaShop 8 does not load a module's own XLIFF files during install or for a
            // locale other than the current one: read them directly (e-mails in the customer's language).
            $own = $this->xliff($section, $locale);
            if (isset($own[$text]) && $own[$text] !== '') {
                $out = strtr($own[$text], $params);
            }
        }

        return $out;
    }

    /** source => target of translations/<locale>/ModulesCartrecovery<Section>.<locale>.xlf */
    protected function xliff($section, $locale)
    {
        static $cache = [];
        $key = $section . '|' . $locale;
        if (!isset($cache[$key])) {
            $cache[$key] = [];
            $file = $this->getLocalPath() . 'translations/' . $locale . '/ModulesCartrecovery' . $section . '.' . $locale . '.xlf';
            if (is_file($file) && ($xml = @simplexml_load_file($file))) {
                $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
                foreach ($xml->xpath('//x:trans-unit') ?: [] as $unit) {
                    $cache[$key][(string) $unit->source] = (string) $unit->target;
                }
            }
        }

        return $cache[$key];
    }

    /* =====================================================================
     *  Install / uninstall
     * =================================================================== */

    public function getDefaults()
    {
        return [
            'CARTREC_AUTO' => 0,
            'CARTREC_STEP1_DELAY' => 3,
            'CARTREC_STEP2_ON' => 1,
            'CARTREC_STEP2_DELAY' => 24,
            'CARTREC_STEP3_ON' => 0,
            'CARTREC_STEP3_DELAY' => 72,
            'CARTREC_STEP1_DISC' => 0,
            'CARTREC_STEP2_DISC' => 1,
            'CARTREC_STEP3_DISC' => 1,
            'CARTREC_TRACK' => 1,
            'CARTREC_MAXAGE' => 7,
            'CARTREC_MINAMOUNT' => 0,
            'CARTREC_BCC' => '',
            'CARTREC_AUDIENCE' => 'all',
            'CARTREC_TEST_EMAIL' => (string) Configuration::get('PS_SHOP_EMAIL'),
            'CARTREC_LASTRUN' => '',
            'CARTREC_TPL' => 'classic',
            'CARTREC_ACCENT' => self::$templates['classic']['accent'],
            'CARTREC_HEADER' => self::$templates['classic']['header'],
            'CARTREC_REAS' => 1,
            'CARTREC_HERO' => '',
            'CARTREC_DISC' => 0,
            'CARTREC_DISC_TYPE' => 'percent',
            'CARTREC_DISC_VALUE' => 10,
            'CARTREC_DISC_DAYS' => 7,
            'CARTREC_DISC_MIN' => 0,
            'CARTREC_DISC_PREFIX' => 'CART',
            'CARTREC_DISC_APPLY' => 1,
        ];
    }

    /** All multilingual keys (step texts expanded). */
    public static function langKeys()
    {
        $keys = self::SHARED_TEXT_KEYS;
        for ($i = 1; $i <= self::MAX_STEPS; ++$i) {
            foreach (self::STEP_TEXT_KEYS as $k) {
                $keys[] = $k . '_' . $i;
            }
        }

        return $keys;
    }

    /** Default e-mail texts in one language. */
    public function getDefaultTexts($locale)
    {
        $t = function ($s) use ($locale) {
            return $this->t($s, [], 'Emails', $locale);
        };
        $p = function ($s) {
            return '<p>' . $s . '</p>';
        };
        $sign = $p($t('Any questions? Just reply to this e-mail.') . '<br />' . $t('See you soon,') . '<br />' . $t('The {shop_name} team'));

        return [
            'CARTREC_SUBJECT_1' => $t('{firstname}, your cart is waiting for you'),
            'CARTREC_TITLE_1' => $t('Your cart is waiting'),
            'CARTREC_BODY_1' => $p($t('Hello {firstname},'))
                . $p($t('You left a few items in your cart at {shop_name}. We have kept them for you.'))
                . '<p>{cart_products}</p><p>{discount_block}</p>'
                . $p($t('Click the button below to get your cart back and complete your order:'))
                . '<p>{cart_link}</p>' . $sign,
            'CARTREC_SUBJECT_2' => $t('Still thinking it over, {firstname}?'),
            'CARTREC_TITLE_2' => $t('Still thinking it over?'),
            'CARTREC_BODY_2' => $p($t('Hello {firstname},'))
                . $p($t('Your cart at {shop_name} is still saved. If you have a question about a product, delivery or payment, just reply to this e-mail: we are happy to help.'))
                . '<p>{cart_products}</p><p>{discount_block}</p><p>{cart_link}</p>' . $sign,
            'CARTREC_SUBJECT_3' => $t('Last reminder: your cart at {shop_name}'),
            'CARTREC_TITLE_3' => $t('Last reminder'),
            'CARTREC_BODY_3' => $p($t('Hello {firstname},'))
                . $p($t('This is our last e-mail about the items you left in your cart. If you still want them, your cart is one click away.'))
                . '<p>{cart_products}</p><p>{discount_block}</p><p>{cart_link}</p>'
                . $p($t('Thank you,') . '<br />' . $t('The {shop_name} team')),
            'CARTREC_BTN' => $t('Complete my order'),
            'CARTREC_FOOTER' => $t('You are receiving this e-mail because you created a cart in our shop.'),
            'CARTREC_REAS_TEXT' => $t('Secure payment') . ' | ' . $t('Your data is protected') . "\n"
                . $t('Tracked delivery') . ' | ' . $t('On every order') . "\n"
                . $t('Customer service') . ' | ' . $t('We reply quickly'),
            'CARTREC_TAGLINE' => '',
        ];
    }

    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }
        foreach ($this->getDefaults() as $key => $value) {
            Configuration::updateValue($key, $value);
        }
        $this->installTexts();
        $this->copyMailTemplates();
        Configuration::updateValue('CARTREC_TOKEN', Tools::passwdGen(32));

        return $this->registerHook([
            'actionMailAlterMessageBeforeSend',
            'actionObjectLanguageAddAfter',
            'registerGDPRConsent',
            'actionDeleteGDPRCustomer',
            'actionExportGDPRData',
        ]);
    }

    /** Fills the multilingual texts for every installed language. */
    public function installTexts()
    {
        $values = [];
        foreach (Language::getLanguages(false) as $lang) {
            $locale = !empty($lang['locale']) ? $lang['locale'] : $lang['language_code'];
            foreach ($this->getDefaultTexts($locale) as $key => $text) {
                $values[$key][(int) $lang['id_lang']] = $text;
            }
        }
        foreach ($values as $key => $byLang) {
            Configuration::updateValue($key, $byLang, strpos($key, 'CARTREC_BODY') === 0);
        }
    }

    /**
     * The e-mail templates hold no text (it all comes from the settings), so every language uses
     * the same files. Copies them for installed languages the module does not ship.
     */
    public function copyMailTemplates()
    {
        $dir = $this->getLocalPath() . 'mails/';
        foreach (Language::getLanguages(false) as $lang) {
            $iso = preg_replace('/[^a-z-]/', '', strtolower($lang['iso_code']));
            if ($iso === '' || is_dir($dir . $iso)) {
                continue;
            }
            if (@mkdir($dir . $iso, 0755)) {
                foreach (glob($dir . 'en/*') ?: [] as $file) {
                    @copy($file, $dir . $iso . '/' . basename($file));
                }
            }
        }

        return true;
    }

    /** A language added later gets the templates and the default texts. */
    public function hookActionObjectLanguageAddAfter($params)
    {
        $this->copyMailTemplates();
        if (empty($params['object']) || !Validate::isLoadedObject($params['object'])) {
            return;
        }
        $lang = $params['object'];
        $locale = !empty($lang->locale) ? $lang->locale : $lang->language_code;
        foreach ($this->getDefaultTexts($locale) as $key => $text) {
            $values = [];
            foreach (Language::getLanguages(false) as $l) {
                $values[(int) $l['id_lang']] = (int) $l['id_lang'] === (int) $lang->id ? $text : (string) Configuration::get($key, (int) $l['id_lang']);
            }
            Configuration::updateValue($key, $values, strpos($key, 'CARTREC_BODY') === 0);
        }
    }

    public function uninstall()
    {
        foreach (array_keys($this->getDefaults()) as $key) {
            Configuration::deleteByName($key);
        }
        foreach (self::langKeys() as $key) {
            Configuration::deleteByName($key);
        }
        foreach (glob($this->getLocalPath() . 'img/hero_*') ?: [] as $file) {
            @unlink($file);
        }
        Configuration::deleteByName('CARTREC_TOKEN');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE_UNSUB . '`');

        return parent::uninstall();
    }

    protected function installDb()
    {
        $db = Db::getInstance();

        return $db->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
                `id_reminder` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT UNSIGNED NOT NULL,
                `step` TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `id_customer` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
                `id_lang` INT UNSIGNED NOT NULL DEFAULT 0,
                `email` VARCHAR(255) NOT NULL,
                `mode` VARCHAR(10) NOT NULL DEFAULT \'manual\',
                `sent_count` INT UNSIGNED NOT NULL DEFAULT 1,
                `id_cart_rule` INT UNSIGNED NOT NULL DEFAULT 0,
                `discount_code` VARCHAR(64) NOT NULL DEFAULT \'\',
                `opens` INT UNSIGNED NOT NULL DEFAULT 0,
                `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_open` DATETIME NULL DEFAULT NULL,
                `date_click` DATETIME NULL DEFAULT NULL,
                `date_sent` DATETIME NOT NULL,
                PRIMARY KEY (`id_reminder`),
                UNIQUE KEY `cart_step` (`id_cart`, `step`),
                KEY `id_customer` (`id_customer`),
                KEY `email` (`email`(191))
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4')
            && $db->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE_UNSUB . '` (
                `email` VARCHAR(255) NOT NULL,
                `id_customer` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`email`(191))
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4');
    }

    /* =====================================================================
     *  Back office
     * =================================================================== */

    public function getContent()
    {
        return (new Admin($this))->handle();
    }

    public function renderAuthor()
    {
        $this->context->smarty->assign('author_img', $this->_path . 'views/img/author.jpg');

        return $this->display(__FILE__, 'views/templates/admin/author.tpl');
    }

    /* =====================================================================
     *  Services
     * =================================================================== */

    public function repo()
    {
        return new Repository($this);
    }

    public function mailer()
    {
        return new Mailer($this);
    }

    public function discount()
    {
        return new Discount($this);
    }

    /** Locale code (de-DE) of a language id. */
    public function localeOf($idLang)
    {
        static $cache = [];
        $idLang = (int) $idLang;
        if (!isset($cache[$idLang])) {
            $lang = new Language($idLang);
            $cache[$idLang] = Validate::isLoadedObject($lang)
                ? (!empty($lang->locale) ? $lang->locale : $lang->language_code)
                : 'en-US';
        }

        return $cache[$idLang];
    }

    /* =====================================================================
     *  Cron, unsubscribe
     * =================================================================== */

    public function getCronToken()
    {
        $token = (string) Configuration::get('CARTREC_TOKEN');
        if ($token === '') {
            $token = Tools::passwdGen(32);
            Configuration::updateValue('CARTREC_TOKEN', $token);
        }

        return $token;
    }

    public function getCronUrl($idShop = null)
    {
        return $this->context->link->getModuleLink($this->name, 'cron', ['token' => $this->getCronToken()], true, null, $idShop);
    }

    /** Signed, customer-specific unsubscribe link. */
    public function getUnsubscribeUrl($idCustomer, $idLang, $idShop = null)
    {
        return $this->context->link->getModuleLink($this->name, 'unsubscribe', [
            'u' => (int) $idCustomer,
            't' => $this->unsubscribeToken($idCustomer),
        ], true, (int) $idLang, $idShop);
    }

    public function unsubscribeToken($idCustomer)
    {
        return substr(hash_hmac('sha256', 'unsub|' . (int) $idCustomer, $this->getCronToken() . _COOKIE_KEY_), 0, 32);
    }

    /** Highest reminder step that is switched on (1 to 3). */
    public static function maxStep()
    {
        if (!(int) Configuration::get('CARTREC_STEP2_ON')) {
            return 1;
        }

        return (int) Configuration::get('CARTREC_STEP3_ON') ? 3 : 2;
    }

    public static function stepDelay($step)
    {
        return max(1, (int) Configuration::get('CARTREC_STEP' . (int) $step . '_DELAY'));
    }

    public function trackToken($idReminder)
    {
        return substr(hash_hmac('sha256', 'track|' . (int) $idReminder, $this->getCronToken() . _COOKIE_KEY_), 0, 20);
    }

    /** Open pixel (a=o) or click redirect to the cart (a=c) of one sent reminder. */
    public function getTrackUrl($idReminder, $action, $idLang, $idShop = null)
    {
        return $this->context->link->getModuleLink($this->name, 'track', [
            'r' => (int) $idReminder,
            'a' => $action,
            't' => $this->trackToken($idReminder),
        ], true, (int) $idLang, $idShop);
    }

    /** @return array summary for the cron output */
    public function runAutomatic()
    {
        $summary = ['date' => date('Y-m-d H:i:s'), 'sent' => 0, 'errors' => 0, 'skipped' => 0, 'enabled' => (int) Configuration::get('CARTREC_AUTO')];

        // Expired, unused discount codes are cleaned up on every run.
        $summary['cleaned'] = $this->discount()->cleanup();

        if (!$summary['enabled']) {
            Configuration::updateValue('CARTREC_LASTRUN', json_encode($summary));

            return $summary;
        }

        $maxAge = max(1, (int) Configuration::get('CARTREC_MAXAGE'));
        $minAmount = (float) Configuration::get('CARTREC_MINAMOUNT');
        $audience = (string) Configuration::get('CARTREC_AUDIENCE');
        $maxStep = self::maxStep();
        $repo = $this->repo();

        $rows = array_reverse($repo->getCartRows($maxAge)); // oldest first
        foreach ($rows as $r) {
            if ($summary['sent'] >= self::CRON_BATCH) {
                break;
            }
            if ($r['status'] === 'won') {
                continue;
            }
            $step = (int) $r['last_step'] + 1;
            if ($step > $maxStep || strtotime($r['date_upd']) > time() - self::stepDelay($step) * 3600) {
                continue;
            }
            // Never two reminders of the same cart less than an hour apart.
            if ($r['date_sent'] && strtotime($r['date_sent']) > time() - 3600) {
                continue;
            }
            if (!(int) $r['active'] || $r['unsubscribed']
                || ($audience === 'newsletter' && !(int) $r['newsletter'])
                || ($audience === 'optin' && !(int) $r['optin'])) {
                ++$summary['skipped'];
                continue;
            }
            if ($minAmount > 0) {
                $sum = $repo->getCartSummary((int) $r['id_cart']);
                if ($sum['total'] < $minAmount) {
                    continue;
                }
            }
            $res = $this->mailer()->sendReminder((int) $r['id_cart'], 'auto', null, $step);
            if ($res === true) {
                ++$summary['sent'];
            } else {
                ++$summary['errors'];
                PrestaShopLogger::addLog('[cartrecovery] ' . $res, 2, null, 'Cart', (int) $r['id_cart'], true);
            }
        }

        Configuration::updateValue('CARTREC_LASTRUN', json_encode($summary));

        return $summary;
    }

    /* =====================================================================
     *  Hooks
     * =================================================================== */

    /** Adds a List-Unsubscribe header to our reminders (Swift Mailer on PS 8, Symfony Mailer on PS 9). */
    public function hookActionMailAlterMessageBeforeSend($params)
    {
        if (!self::$sendingUnsubUrl || empty($params['message']) || !is_object($params['message'])) {
            return;
        }
        $message = $params['message'];
        if (method_exists($message, 'getHeaders')) {
            $headers = $message->getHeaders();
            if (method_exists($headers, 'addTextHeader')) {
                $headers->addTextHeader('List-Unsubscribe', '<' . self::$sendingUnsubUrl . '>');
                // RFC 8058 one-click: the mail client POSTs to the URL.
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
        }
    }

    public function hookRegisterGDPRConsent($params)
    {
        // Lists the module in the official GDPR module.
    }

    public function hookActionDeleteGDPRCustomer($customer)
    {
        if (!empty($customer['email']) && Validate::isEmail($customer['email'])) {
            $this->repo()->deleteByEmail((string) $customer['email']);
        }

        return json_encode(true);
    }

    public function hookActionExportGDPRData($customer)
    {
        if (empty($customer['email']) || !Validate::isEmail($customer['email'])) {
            return json_encode([]);
        }

        return json_encode($this->repo()->exportByEmail((string) $customer['email']));
    }
}

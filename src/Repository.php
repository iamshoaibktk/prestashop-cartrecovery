<?php
/**
 * Abandoned Cart Recovery: abandoned carts, reminders, unsubscribes and stats.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace Iamshoaibktk\Cartrecovery;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Repository
{
    /** @var \Cartrecovery */
    private $module;

    public function __construct(\Cartrecovery $module)
    {
        $this->module = $module;
    }

    /**
     * Carts of registered and guest customers with products, updated in the last $days days,
     * in the current shop. Each row gets a computed `status`:
     *  - none : abandoned, not reminded yet
     *  - sent : reminded, no order yet
     *  - won  : reminded, then ordered (recovered)
     * Carts that became an order without a reminder, or that were replaced by a newer cart of the
     * same customer, are not abandoned and are left out.
     */
    public function getCartRows($days, $search = '')
    {
        $p = _DB_PREFIX_;
        $idShop = (int) \Context::getContext()->shop->id;
        $where = '';
        if ($search !== '') {
            $s = pSQL('%' . $search . '%');
            $where = " AND (cu.firstname LIKE '$s' OR cu.lastname LIKE '$s' OR cu.email LIKE '$s' OR CONCAT(cu.firstname, ' ', cu.lastname) LIKE '$s' OR c.id_cart = " . (int) $search . ')';
        }

        $rows = \Db::getInstance()->executeS('
            SELECT c.id_cart, c.id_customer, c.id_lang, c.date_upd,
                cu.firstname, cu.lastname, cu.email, cu.active, cu.is_guest, cu.newsletter, cu.optin,
                r.last_step, r.date_sent, r.mode, r.sent_count, r.discount_code, r.date_open, r.date_click,
                IF(u.email IS NULL, 0, 1) AS unsubscribed,
                (SELECT o.id_order FROM `' . $p . 'orders` o WHERE o.id_cart = c.id_cart ORDER BY o.id_order ASC LIMIT 1) AS id_order_cart,
                (SELECT o2.id_order FROM `' . $p . 'orders` o2 WHERE o2.id_customer = c.id_customer AND o2.date_add >= c.date_upd ORDER BY o2.date_add ASC LIMIT 1) AS id_order_after,
                (SELECT COUNT(*) FROM `' . $p . 'cart` c3 WHERE c3.id_customer = c.id_customer AND c3.id_cart > c.id_cart
                    AND EXISTS (SELECT 1 FROM `' . $p . 'cart_product` cp3 WHERE cp3.id_cart = c3.id_cart)) AS newer_carts
            FROM `' . $p . 'cart` c
            INNER JOIN `' . $p . 'customer` cu ON cu.id_customer = c.id_customer AND cu.deleted = 0
            LEFT JOIN (
                SELECT id_cart, MAX(step) AS last_step, MAX(date_sent) AS date_sent, SUM(sent_count) AS sent_count,
                    SUBSTRING_INDEX(GROUP_CONCAT(mode ORDER BY step DESC), \',\', 1) AS mode,
                    SUBSTRING_INDEX(GROUP_CONCAT(NULLIF(discount_code, \'\') ORDER BY step DESC), \',\', 1) AS discount_code,
                    MIN(date_open) AS date_open, MIN(date_click) AS date_click
                FROM `' . $p . \Cartrecovery::TABLE . '` GROUP BY id_cart
            ) r ON r.id_cart = c.id_cart
            LEFT JOIN `' . $p . \Cartrecovery::TABLE_UNSUB . '` u ON u.email = cu.email
            WHERE c.id_customer > 0
              AND c.id_shop = ' . $idShop . '
              AND c.date_upd >= \'' . pSQL(date('Y-m-d H:i:s', time() - (int) $days * 86400)) . '\'
              AND EXISTS (SELECT 1 FROM `' . $p . 'cart_product` cp WHERE cp.id_cart = c.id_cart)
              ' . $where . '
            ORDER BY c.date_upd DESC
            LIMIT 2000');

        $out = [];
        foreach ((array) $rows as $r) {
            $ordered = (int) $r['id_order_cart'] ?: (int) $r['id_order_after'];
            if ($r['date_sent']) {
                $r['status'] = $ordered ? 'won' : 'sent';
            } else {
                if ($ordered || (int) $r['newer_carts'] > 0) {
                    continue;
                }
                $r['status'] = 'none';
            }
            $r['id_order'] = $ordered;
            $r['last_step'] = (int) $r['last_step'];
            $out[] = $r;
        }

        return $out;
    }

    /**
     * Everything needed for an e-mail or a list row. Returns null when the cart is gone or empty.
     */
    public function loadCartData($idCart, $idLang = null)
    {
        $cart = new \Cart((int) $idCart);
        if (!\Validate::isLoadedObject($cart) || !$cart->id_customer) {
            return null;
        }
        $customer = new \Customer((int) $cart->id_customer);
        if (!\Validate::isLoadedObject($customer)) {
            return null;
        }
        $idLang = (int) ($idLang ?: $cart->id_lang ?: \Configuration::get('PS_LANG_DEFAULT'));

        $context = \Context::getContext();
        $prevCart = $context->cart;
        $prevCurrency = $context->currency;
        $context->cart = $cart;
        $currency = new \Currency((int) $cart->id_currency);
        if (\Validate::isLoadedObject($currency)) {
            $context->currency = $currency;
        }

        $products = [];
        $count = 0;
        $imgType = \ImageType::getFormattedName('small');
        try {
            foreach ($cart->getProducts(true, false, $idLang) as $prod) {
                $qty = (int) $prod['cart_quantity'];
                $count += $qty;
                $img = '';
                if (!empty($prod['id_image'])) {
                    $img = $context->link->getImageLink($prod['link_rewrite'], $prod['id_image'], $imgType);
                }
                $products[] = [
                    'name' => $prod['name'],
                    'attributes' => isset($prod['attributes']) ? (string) $prod['attributes'] : '',
                    'qty' => $qty,
                    'img' => $img,
                    'total' => (float) $prod['total_wt'],
                ];
            }
            $total = (float) $cart->getOrderTotal(true, \Cart::BOTH_WITHOUT_SHIPPING);
        } catch (\Throwable $e) {
            $products = [];
            $total = 0;
        }

        $context->cart = $prevCart;
        $context->currency = $prevCurrency;

        if (!$products) {
            return null;
        }

        $locale = $this->module->localeOf($idLang);
        foreach ($products as &$prod) {
            $prod['total_display'] = $this->formatPrice($prod['total'], $currency, $idLang);
        }
        unset($prod);

        return [
            'cart' => $cart,
            'customer' => $customer,
            'currency' => $currency,
            'id_lang' => $idLang,
            'locale' => $locale,
            'products' => $products,
            'count' => $count,
            'total' => $total,
            'total_display' => $this->formatPrice($total, $currency, $idLang),
        ];
    }

    /** Products, total and first image of a cart (for the list). */
    public function getCartSummary($idCart)
    {
        $data = $this->loadCartData($idCart, (int) \Context::getContext()->language->id);
        if (!$data) {
            return ['product_name' => '', 'product_img' => '', 'count' => 0, 'total_display' => '', 'total' => 0];
        }
        $first = $data['products'][0];

        return [
            'product_name' => $first['name'],
            'product_img' => $first['img'],
            'count' => $data['count'],
            'total_display' => $data['total_display'],
            'total' => $data['total'],
        ];
    }

    /** Price in the currency and number format of a language. */
    public function formatPrice($amount, $currency = null, $idLang = null)
    {
        $context = \Context::getContext();
        try {
            if (!$currency || !\Validate::isLoadedObject($currency)) {
                $currency = $context->currency ?: \Currency::getDefaultCurrency();
            }
            if ($idLang && (int) $idLang !== (int) $context->language->id) {
                // The context locale is cached for the back office language: ask the repository for the e-mail one.
                $lang = new \Language((int) $idLang);
                $container = (new \PrestaShop\PrestaShop\Adapter\ContainerFinder($context))->getContainer();
                $locale = $container->get(\Tools::SERVICE_LOCALE_REPOSITORY)->getLocale($lang->getLocale());
            } else {
                $locale = $context->getCurrentLocale() ?: \Tools::getContextLocale($context);
            }

            return $locale->formatPrice($amount, $currency->iso_code);
        } catch (\Throwable $e) {
            return number_format((float) $amount, 2, '.', ' ') . ' ' . ($currency ? $currency->iso_code : '');
        }
    }

    public function getStats($days)
    {
        $rows = $this->getCartRows($days);
        $stats = ['abandoned' => count($rows), 'pending' => 0, 'sent' => 0, 'opened' => 0, 'clicked' => 0, 'won' => 0, 'won_amount' => 0.0, 'rate' => 0];
        $orderIds = [];
        foreach ($rows as $r) {
            if ($r['status'] === 'none') {
                ++$stats['pending'];
            } else {
                ++$stats['sent'];
                $stats['opened'] += $r['date_open'] ? 1 : 0;
                $stats['clicked'] += $r['date_click'] ? 1 : 0;
            }
            if ($r['status'] === 'won') {
                ++$stats['won'];
                $orderIds[] = (int) $r['id_order'];
            }
        }
        if ($orderIds) {
            $stats['won_amount'] = (float) \Db::getInstance()->getValue('
                SELECT SUM(total_paid_tax_incl / conversion_rate) FROM `' . _DB_PREFIX_ . 'orders`
                WHERE id_order IN (' . implode(',', array_unique($orderIds)) . ')');
        }
        $stats['rate'] = $stats['sent'] ? round($stats['won'] * 100 / $stats['sent']) : 0;
        $stats['won_amount_display'] = $this->formatPrice($stats['won_amount'], \Currency::getDefaultCurrency());

        return $stats;
    }

    /* ---------------- Reminder records ---------------- */

    public function getReminder($idCart, $step)
    {
        $row = \Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '`
            WHERE id_cart = ' . (int) $idCart . ' AND step = ' . (int) $step);

        return $row ?: null;
    }

    public function getReminderById($idReminder)
    {
        $row = \Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '`
            WHERE id_reminder = ' . (int) $idReminder);

        return $row ?: null;
    }

    public function lastStep($idCart)
    {
        return (int) \Db::getInstance()->getValue('SELECT MAX(step) FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '`
            WHERE id_cart = ' . (int) $idCart);
    }

    /** Latest discount (cart rule id) given for this cart, whatever the step. */
    public function lastCartRule($idCart)
    {
        return (int) \Db::getInstance()->getValue('SELECT id_cart_rule FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '`
            WHERE id_cart = ' . (int) $idCart . ' AND id_cart_rule > 0 ORDER BY step DESC');
    }

    /**
     * Creates (or finds) the record of a reminder before it is sent, so the e-mail can carry
     * its tracking id. Automatic reminders claim the row: a second cron run gets null and sends nothing.
     *
     * @return array|null ['id' => id_reminder, 'new' => bool]
     */
    public function prepare($idCart, $step, $mode, array $data)
    {
        $db = \Db::getInstance();
        $existing = $this->getReminder($idCart, $step);
        if ($existing) {
            return $mode === 'auto' ? null : ['id' => (int) $existing['id_reminder'], 'new' => false];
        }
        $db->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '`
            (id_cart, step, id_customer, id_shop, id_lang, email, mode, sent_count, date_sent)
            VALUES (' . (int) $idCart . ', ' . (int) $step . ', ' . (int) $data['id_customer'] . ', ' . (int) $data['id_shop'] . ', '
            . (int) $data['id_lang'] . ', \'' . pSQL($data['email']) . '\', \'' . pSQL($mode) . '\', 1, \'' . date('Y-m-d H:i:s') . '\')');
        if ($db->Affected_Rows() < 1) {
            return null;
        }

        return ['id' => (int) $db->Insert_ID(), 'new' => true];
    }

    public function release($idReminder)
    {
        \Db::getInstance()->delete(\Cartrecovery::TABLE, 'id_reminder = ' . (int) $idReminder);
    }

    /** Marks a reminder as sent (a resend of an existing record counts one more send). */
    public function record($idReminder, $isNew, $mode, array $data)
    {
        $set = [
            '`mode` = \'' . pSQL($mode) . '\'',
            '`date_sent` = \'' . date('Y-m-d H:i:s') . '\'',
            '`id_lang` = ' . (int) $data['id_lang'],
            '`email` = \'' . pSQL($data['email']) . '\'',
        ];
        if (isset($data['id_cart_rule'])) {
            $set[] = '`id_cart_rule` = ' . (int) $data['id_cart_rule'];
            $set[] = '`discount_code` = \'' . pSQL($data['discount_code']) . '\'';
        }
        if (!$isNew) {
            $set[] = '`sent_count` = `sent_count` + 1';
        }

        return \Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '` SET ' . implode(', ', $set)
            . ' WHERE id_reminder = ' . (int) $idReminder);
    }

    /** Counts an open or a click (first date kept). */
    public function track($idReminder, $what)
    {
        $col = $what === 'click' ? 'click' : 'open';
        $sql = 'UPDATE `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '` SET `' . $col . 's` = `' . $col . 's` + 1,
            `date_' . $col . '` = IFNULL(`date_' . $col . '`, \'' . date('Y-m-d H:i:s') . '\')';
        if ($col === 'click') {
            // A click proves the e-mail was opened, even with images blocked.
            $sql .= ', `date_open` = IFNULL(`date_open`, \'' . date('Y-m-d H:i:s') . '\')';
        }

        return \Db::getInstance()->execute($sql . ' WHERE id_reminder = ' . (int) $idReminder);
    }

    /* ---------------- Unsubscribe ---------------- */

    public function isUnsubscribed($email)
    {
        return (bool) \Db::getInstance()->getValue('SELECT 1 FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE_UNSUB . '`
            WHERE email = \'' . pSQL($email) . '\'');
    }

    public function unsubscribe($email, $idCustomer, $idShop)
    {
        return \Db::getInstance()->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . \Cartrecovery::TABLE_UNSUB . '`
            (email, id_customer, id_shop, date_add) VALUES (\'' . pSQL($email) . '\', ' . (int) $idCustomer . ', ' . (int) $idShop . ', \'' . date('Y-m-d H:i:s') . '\')');
    }

    public function resubscribe($email)
    {
        return \Db::getInstance()->delete(\Cartrecovery::TABLE_UNSUB, 'email = \'' . pSQL($email) . '\'');
    }

    public function countUnsubscribed()
    {
        return (int) \Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE_UNSUB . '`');
    }

    /* ---------------- GDPR ---------------- */

    public function deleteByEmail($email)
    {
        $db = \Db::getInstance();
        $db->delete(\Cartrecovery::TABLE, 'email = \'' . pSQL($email) . '\'');

        return true;
    }

    public function exportByEmail($email)
    {
        $rows = (array) \Db::getInstance()->executeS('SELECT id_cart, step, mode, sent_count, discount_code, date_sent
            FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE . '` WHERE email = \'' . pSQL($email) . '\' ORDER BY date_sent');
        $unsub = \Db::getInstance()->getValue('SELECT date_add FROM `' . _DB_PREFIX_ . \Cartrecovery::TABLE_UNSUB . '`
            WHERE email = \'' . pSQL($email) . '\'');
        foreach ($rows as &$r) {
            $r = [
                $this->module->t('Cart reminder: cart') => (int) $r['id_cart'],
                $this->module->t('Reminder') => (int) $r['step'],
                $this->module->t('Sent on') => $r['date_sent'],
                $this->module->t('Times sent') => (int) $r['sent_count'],
                $this->module->t('Discount code') => $r['discount_code'],
            ];
        }
        unset($r);
        if ($unsub) {
            $rows[] = [$this->module->t('Unsubscribed from cart reminders on') => $unsub];
        }

        return (array) $rows;
    }
}

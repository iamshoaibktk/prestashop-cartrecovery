<?php
/**
 * Abandoned Cart Recovery: open pixel and click redirect of the reminder e-mails.
 *
 * a=o returns a 1x1 GIF and counts an open; a=c counts a click and opens the customer's cart.
 * Links are signed per reminder, and a click can only ever lead to that reminder's own cart.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CartrecoveryTrackModuleFrontController extends ModuleFrontController
{
    /** @var Cartrecovery */
    public $module;

    public $display_header = false;
    public $display_footer = false;
    public $content_only = true;

    public function initContent()
    {
        $id = (int) Tools::getValue('r');
        $valid = $id && hash_equals($this->module->trackToken($id), (string) Tools::getValue('t'));
        $repo = $this->module->repo();

        if (Tools::getValue('a') === 'c') {
            $row = $valid ? $repo->getReminderById($id) : null;
            if ($row) {
                $repo->track($id, 'click');
                $cart = new Cart((int) $row['id_cart']);
                if (Validate::isLoadedObject($cart)) {
                    Tools::redirect($this->module->mailer()->getCartUrl($cart, (int) $row['id_lang']));
                }
            }
            Tools::redirect($this->context->link->getPageLink('cart', true, null, 'action=show'));
        }

        if ($valid) {
            $repo->track($id, 'open');
        }
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex, nofollow');
        exit(base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    }
}

<?php
/**
 * Abandoned Cart Recovery: unsubscribe page (link at the bottom of every reminder).
 *
 * Opening the link shows a confirmation button, so link scanners in mail servers
 * never unsubscribe anyone by themselves. Mail clients that support one-click
 * unsubscribe (RFC 8058) POST to the same URL.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CartrecoveryUnsubscribeModuleFrontController extends ModuleFrontController
{
    /** @var Cartrecovery */
    public $module;

    public function initContent()
    {
        parent::initContent();

        $idCustomer = (int) Tools::getValue('u');
        $token = (string) Tools::getValue('t');
        $customer = new Customer($idCustomer);
        $state = 'invalid';
        $repo = $this->module->repo();

        if ($idCustomer && Validate::isLoadedObject($customer) && hash_equals($this->module->unsubscribeToken($idCustomer), $token)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && Tools::getValue('action') === 'resubscribe') {
                $repo->resubscribe($customer->email);
                $state = 'resubscribed';
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $repo->unsubscribe($customer->email, (int) $customer->id, (int) $this->context->shop->id);
                $state = 'done';
            } else {
                $state = $repo->isUnsubscribed($customer->email) ? 'done' : 'confirm';
            }
        }

        $this->context->smarty->assign([
            'cr_state' => $state,
            'cr_action' => $this->context->link->getModuleLink($this->module->name, 'unsubscribe', ['u' => $idCustomer, 't' => $token], true),
            'cr_home' => $this->context->link->getPageLink('index', true),
            'cr_email' => $state !== 'invalid' ? $this->maskEmail($customer->email) : '',
        ]);

        $this->setTemplate('module:cartrecovery/views/templates/front/unsubscribe.tpl');
    }

    /** j***@example.com: enough to recognise, not enough to leak. */
    protected function maskEmail($email)
    {
        $parts = explode('@', (string) $email, 2);

        return Tools::substr($parts[0], 0, 1) . '***@' . (isset($parts[1]) ? $parts[1] : '');
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();
        $breadcrumb['links'][] = [
            'title' => $this->module->t('Cart reminders', [], 'Shop'),
            'url' => '',
        ];

        return $breadcrumb;
    }

    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();
        $page['meta']['robots'] = 'noindex';
        $page['meta']['title'] = $this->module->t('Cart reminders', [], 'Shop');

        return $page;
    }
}

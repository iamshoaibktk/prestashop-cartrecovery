<?php
/**
 * Abandoned Cart Recovery: cron entry point for automatic reminders.
 *
 * URL (shown in the module settings): https://<shop>/module/cartrecovery/cron?token=<token>
 * Call it every hour, e.g. curl -s "<url>" > /dev/null
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class CartrecoveryCronModuleFrontController extends ModuleFrontController
{
    /** @var Cartrecovery */
    public $module;

    public $display_header = false;
    public $display_footer = false;
    public $content_only = true;

    public function initContent()
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        $token = (string) Tools::getValue('token');
        if ($token === '' || !hash_equals($this->module->getCronToken(), $token)) {
            http_response_code(403);
            exit('Invalid token');
        }

        $summary = $this->module->runAutomatic();
        if (!$summary['enabled']) {
            exit(sprintf('Automatic sending is off in the module settings. Expired codes removed: %d', $summary['cleaned']));
        }

        exit(sprintf(
            'OK %s - reminders sent: %d, errors: %d, skipped (unsubscribed or no consent): %d, expired codes removed: %d',
            $summary['date'],
            $summary['sent'],
            $summary['errors'],
            $summary['skipped'],
            $summary['cleaned']
        ));
    }
}

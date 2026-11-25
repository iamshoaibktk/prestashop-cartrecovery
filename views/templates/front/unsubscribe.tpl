{**
 * Abandoned Cart Recovery: unsubscribe page.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{extends file='page.tpl'}

{block name='page_title'}
  {l s='Cart reminders' d='Modules.Cartrecovery.Shop'}
{/block}

{block name='page_content'}
  <div class="cartrecovery-unsub" style="max-width:560px">
    {if $cr_state == 'confirm'}
      <p>{l s='Do you want to stop receiving e-mails about the carts you leave in our shop?' d='Modules.Cartrecovery.Shop'}</p>
      <p class="text-muted">{$cr_email|escape:'html':'UTF-8'}</p>
      <form method="post" action="{$cr_action|escape:'html':'UTF-8'}">
        <button type="submit" class="btn btn-primary">{l s='Unsubscribe' d='Modules.Cartrecovery.Shop'}</button>
        <a href="{$cr_home|escape:'html':'UTF-8'}" class="btn btn-secondary">{l s='Keep receiving them' d='Modules.Cartrecovery.Shop'}</a>
      </form>
    {elseif $cr_state == 'done'}
      <div class="alert alert-success" role="status">{l s='You are unsubscribed. You will not receive cart reminders any more.' d='Modules.Cartrecovery.Shop'}</div>
      <p class="text-muted">{$cr_email|escape:'html':'UTF-8'}</p>
      <form method="post" action="{$cr_action|escape:'html':'UTF-8'}">
        <input type="hidden" name="action" value="resubscribe">
        <button type="submit" class="btn btn-link px-0">{l s='Unsubscribed by mistake? Receive them again' d='Modules.Cartrecovery.Shop'}</button>
      </form>
      <p><a href="{$cr_home|escape:'html':'UTF-8'}" class="btn btn-primary">{l s='Back to the shop' d='Modules.Cartrecovery.Shop'}</a></p>
    {elseif $cr_state == 'resubscribed'}
      <div class="alert alert-success" role="status">{l s='Done. You will receive cart reminders again.' d='Modules.Cartrecovery.Shop'}</div>
      <p><a href="{$cr_home|escape:'html':'UTF-8'}" class="btn btn-primary">{l s='Back to the shop' d='Modules.Cartrecovery.Shop'}</a></p>
    {else}
      <div class="alert alert-warning" role="alert">{l s='This link is not valid. Please use the link from your latest e-mail.' d='Modules.Cartrecovery.Shop'}</div>
      <p><a href="{$cr_home|escape:'html':'UTF-8'}" class="btn btn-primary">{l s='Back to the shop' d='Modules.Cartrecovery.Shop'}</a></p>
    {/if}
  </div>
{/block}

{**
 * Abandoned Cart Recovery: back office page.
 *
 * @author    iamshoaibktk@gmail.com (WhatsApp: +923459016286)
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
<div class="cr" id="cr-app">

  <div class="cr-head">
    <div class="cr-head__brand">
      <img src="{$cr.logo|escape:'html':'UTF-8'}" alt="" class="cr-head__logo" width="44" height="44">
      <div>
        <h2 class="cr-head__title">{l s='Abandoned Cart Recovery' d='Modules.Cartrecovery.Admin'}</h2>
        <p class="cr-head__sub">{l s='Remind customers about the cart they left, with a one-click link back to it.' d='Modules.Cartrecovery.Admin'}{if $cr.multishop} · <strong>{$cr.shop_name|escape:'html':'UTF-8'}</strong>{/if}</p>
      </div>
    </div>
    <nav class="cr-tabs" aria-label="{l s='Module sections' d='Modules.Cartrecovery.Admin'}">
      <a class="cr-tab{if $cr.tab == 'settings'} is-active{/if}" href="{$cr.urls.settings|escape:'html':'UTF-8'}"{if $cr.tab == 'settings'} aria-current="page"{/if}>{l s='Settings' d='Modules.Cartrecovery.Admin'}</a>
      <a class="cr-tab{if $cr.tab == 'design'} is-active{/if}" href="{$cr.urls.design|escape:'html':'UTF-8'}"{if $cr.tab == 'design'} aria-current="page"{/if}>{l s='Appearance' d='Modules.Cartrecovery.Admin'}</a>
      <a class="cr-tab{if $cr.tab == 'carts'} is-active{/if}" href="{$cr.urls.carts|escape:'html':'UTF-8'}"{if $cr.tab == 'carts'} aria-current="page"{/if}>
        {l s='Abandoned carts' d='Modules.Cartrecovery.Admin'} <span class="cr-count">{$cr.stats.pending|intval}</span>
      </a>
      <a class="cr-tab{if $cr.tab == 'compose'} is-active{/if}" href="{$cr.urls.compose|escape:'html':'UTF-8'}"{if $cr.tab == 'compose'} aria-current="page"{/if}><i class="material-icons" aria-hidden="true">edit</i> {l s='One-off reminder' d='Modules.Cartrecovery.Admin'}</a>
    </nav>
  </div>

  <div class="cr-kpis" role="group" aria-label="{l s='Last 30 days' d='Modules.Cartrecovery.Admin'}">
    <div class="cr-kpi"><div class="cr-kpi__label">{l s='Abandoned · 30 days' d='Modules.Cartrecovery.Admin'}</div><div class="cr-kpi__value">{$cr.stats.abandoned|intval}</div></div>
    <div class="cr-kpi"><div class="cr-kpi__label">{l s='Reminded' d='Modules.Cartrecovery.Admin'}</div><div class="cr-kpi__value">{$cr.stats.sent|intval}</div></div>
    <div class="cr-kpi"><div class="cr-kpi__label">{l s='Opened · clicked' d='Modules.Cartrecovery.Admin'}</div><div class="cr-kpi__value">{$cr.stats.opened|intval} · {$cr.stats.clicked|intval}</div></div>
    <div class="cr-kpi"><div class="cr-kpi__label">{l s='Recovered' d='Modules.Cartrecovery.Admin'}</div><div class="cr-kpi__value">{$cr.stats.won|intval} <span class="cr-kpi__rate">{$cr.stats.rate|intval} %</span></div></div>
    <div class="cr-kpi"><div class="cr-kpi__label">{l s='Recovered revenue' d='Modules.Cartrecovery.Admin'}</div><div class="cr-kpi__value">{$cr.stats.won_amount_display|escape:'html':'UTF-8'}</div></div>
  </div>

{if $cr.tab == 'settings'}
  {* ================================================================= SETTINGS *}
  <form method="post" action="{$cr.urls.settings|escape:'html':'UTF-8'}" class="cr-form" id="cr-settings">
    <input type="hidden" name="cr_step" id="cr-step" value="1">
    <input type="hidden" name="cr_lang" id="cr-lang" value="{$cr.id_lang|intval}">
    <div class="cr-store" hidden>
      {for $i=1 to $cr.max_steps}
        {foreach from=$cr.languages item=lg}
          <input type="hidden" name="CARTREC_SUBJECT_{$i|intval}[{$lg.id|intval}]" data-store="subject" data-step="{$i|intval}" data-lang="{$lg.id|intval}" value="{$cr.texts[$i].subject[$lg.id]|escape:'html':'UTF-8'}">
          <input type="hidden" name="CARTREC_TITLE_{$i|intval}[{$lg.id|intval}]" data-store="title" data-step="{$i|intval}" data-lang="{$lg.id|intval}" value="{$cr.texts[$i].title[$lg.id]|escape:'html':'UTF-8'}">
          <textarea name="CARTREC_BODY_{$i|intval}[{$lg.id|intval}]" data-store="body" data-step="{$i|intval}" data-lang="{$lg.id|intval}">{$cr.texts[$i].body[$lg.id]|escape:'html':'UTF-8'}</textarea>
        {/foreach}
      {/for}
    </div>

    <div class="cr-cols">
      <section class="cr-card cr-cols__main">
        <h3 class="cr-card__head"><i class="material-icons" aria-hidden="true">mail_outline</i> {l s='E-mail content' d='Modules.Cartrecovery.Admin'}</h3>
        <div class="cr-card__body">
          <div class="cr-field">
            <span class="cr-label" id="cr-steps-label">{l s='Reminder' d='Modules.Cartrecovery.Admin'}</span>
            <div class="cr-steptabs" role="tablist" aria-labelledby="cr-steps-label">
              {for $i=1 to $cr.max_steps}
                <button type="button" role="tab" class="cr-steptab{if $i == 1} is-active{/if}" data-step="{$i|intval}" aria-selected="{if $i == 1}true{else}false{/if}">
                  {l s='Reminder %n%' sprintf=['%n%' => $i] d='Modules.Cartrecovery.Admin'}
                  <span class="cr-steptab__when">{l s='after %h% h' sprintf=['%h%' => $cr.cfg.step_delay[$i]] d='Modules.Cartrecovery.Admin'}</span>
                  {if ($i == 2 && !$cr.cfg.step2) || ($i == 3 && (!$cr.cfg.step2 || !$cr.cfg.step3))}<span class="cr-steptab__off">{l s='off' d='Modules.Cartrecovery.Admin'}</span>{/if}
                </button>
              {/for}
            </div>
          </div>

          <div class="cr-field">
            <span class="cr-label" id="cr-lang-label">{l s='Language' d='Modules.Cartrecovery.Admin'}</span>
            <div class="cr-pills" role="radiogroup" aria-labelledby="cr-lang-label">
              {foreach from=$cr.languages item=lg}
                <button type="button" role="radio" class="cr-pill{if $lg.id == $cr.id_lang} is-active{/if}" data-lang="{$lg.id|intval}" aria-checked="{if $lg.id == $cr.id_lang}true{else}false{/if}" title="{$lg.name|escape:'html':'UTF-8'}">{$lg.iso|escape:'html':'UTF-8'}</button>
              {/foreach}
            </div>
            <p class="cr-help">{l s='Each customer gets the e-mail in the language of their cart. Empty fields fall back to the shop default language.' d='Modules.Cartrecovery.Admin'}</p>
          </div>

          <div class="cr-field">
            <label for="cr-f-subject">{l s='Subject' d='Modules.Cartrecovery.Admin'} <span class="cr-muted" id="cr-f-langname"></span></label>
            <input class="form-control" type="text" id="cr-f-subject" data-edit="subject">
            <p class="cr-help">{l s='You can use %vars%.' sprintf=['%vars%' => '{firstname}, {lastname}, {shop_name}, {discount_value}'] d='Modules.Cartrecovery.Admin'}</p>
          </div>
          <div class="cr-field">
            <label for="cr-f-title">{l s='Title in the e-mail' d='Modules.Cartrecovery.Admin'}</label>
            <input class="form-control" type="text" id="cr-f-title" data-edit="title">
          </div>
          <div class="cr-field">
            <label for="cr-f-body">{l s='Message' d='Modules.Cartrecovery.Admin'}</label>
            <textarea class="cr-rte" id="cr-f-body" rows="14"></textarea>
          </div>

          <div class="cr-vars">
            <div class="cr-vars__title">{l s='Variables' d='Modules.Cartrecovery.Admin'} <span class="cr-help">({l s='click to insert' d='Modules.Cartrecovery.Admin'})</span></div>
            <div class="cr-vars__grid">
              {foreach from=$cr.vars key=code item=label}
                <div><button type="button" class="cr-chip" data-copy="{$code|escape:'html':'UTF-8'}">{$code|escape:'html':'UTF-8'}</button> <span>{$label|escape:'html':'UTF-8'}</span></div>
              {/foreach}
            </div>
            <p class="cr-help">{l s='The cart link logs the customer in and opens their exact cart. If it is missing from the message, the button is added at the end.' d='Modules.Cartrecovery.Admin'}</p>
          </div>

          <div class="cr-testrow">
            <div class="cr-field cr-testrow__email">
              <label for="CARTREC_TEST_EMAIL">{l s='Send a test e-mail to' d='Modules.Cartrecovery.Admin'}</label>
              <input class="form-control" type="email" id="CARTREC_TEST_EMAIL" name="CARTREC_TEST_EMAIL" value="{$cr.cfg.test_email|escape:'html':'UTF-8'}">
            </div>
            <button type="submit" name="submitCrPreview" value="1" formtarget="_blank" class="btn btn-default cr-btn"><i class="material-icons" aria-hidden="true">visibility</i> {l s='Preview' d='Modules.Cartrecovery.Admin'}</button>
            <button type="submit" name="submitCrTest" value="1" class="btn btn-default cr-btn cr-btn--outline"><i class="material-icons" aria-hidden="true">send</i> {l s='Send the test' d='Modules.Cartrecovery.Admin'}</button>
          </div>
          <p class="cr-help">{l s='Preview and test use the selected reminder, the selected language and the latest abandoned cart. No real code is created; links open the shop without logging anyone in.' d='Modules.Cartrecovery.Admin'}</p>
        </div>
      </section>

      <div class="cr-cols__side cr-stackcol">
        <section class="cr-card">
          <h3 class="cr-card__head"><i class="material-icons" aria-hidden="true">schedule</i> {l s='Sending rules' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <div class="cr-field">
              <span class="cr-label" id="cr-auto-label">{l s='Automatic sending' d='Modules.Cartrecovery.Admin'}</span>
              <span class="switch prestashop-switch fixed-width-lg" role="radiogroup" aria-labelledby="cr-auto-label">
                <input type="radio" name="CARTREC_AUTO" id="CARTREC_AUTO_on" value="1"{if $cr.cfg.auto} checked="checked"{/if}>
                <label for="CARTREC_AUTO_on">{l s='Yes' d='Modules.Cartrecovery.Admin'}</label>
                <input type="radio" name="CARTREC_AUTO" id="CARTREC_AUTO_off" value="0"{if !$cr.cfg.auto} checked="checked"{/if}>
                <label for="CARTREC_AUTO_off">{l s='No' d='Modules.Cartrecovery.Admin'}</label>
                <a class="slide-button btn"></a>
              </span>
              <p class="cr-help">{l s='No: reminders are only sent from the Abandoned carts tab.' d='Modules.Cartrecovery.Admin'}</p>
            </div>

            <div class="cr-field">
              <span class="cr-label">{l s='Reminders' d='Modules.Cartrecovery.Admin'}</span>
              <div class="cr-steps">
                {for $i=1 to $cr.max_steps}
                  <div class="cr-steprow">
                    {if $i == 1}
                      <span class="cr-steprow__on"><i class="material-icons" aria-hidden="true">check_box</i></span>
                    {else}
                      <input type="hidden" name="CARTREC_STEP{$i|intval}_ON" value="0">
                      <input type="checkbox" class="cr-steprow__on" name="CARTREC_STEP{$i|intval}_ON" id="CARTREC_STEP{$i|intval}_ON" value="1"{if ($i == 2 && $cr.cfg.step2) || ($i == 3 && $cr.cfg.step3)} checked{/if} aria-label="{l s='Send reminder %n%' sprintf=['%n%' => $i] d='Modules.Cartrecovery.Admin'}">
                    {/if}
                    <label for="CARTREC_STEP{$i|intval}_DELAY" class="cr-steprow__name">{l s='Reminder %n% after' sprintf=['%n%' => $i] d='Modules.Cartrecovery.Admin'}</label>
                    <span class="input-group cr-num cr-num--sm">
                      <input class="form-control" type="number" min="1" id="CARTREC_STEP{$i|intval}_DELAY" name="CARTREC_STEP{$i|intval}_DELAY" value="{$cr.cfg.step_delay[$i]|intval}">
                      <span class="input-group-addon">{l s='hours' d='Modules.Cartrecovery.Admin'}</span>
                    </span>
                    <label class="cr-steprow__disc"><input type="hidden" name="CARTREC_STEP{$i|intval}_DISC" value="0"><input type="checkbox" name="CARTREC_STEP{$i|intval}_DISC" value="1"{if $cr.cfg.step_disc[$i]} checked{/if}> {l s='code' d='Modules.Cartrecovery.Admin'}</label>
                  </div>
                {/for}
              </div>
              <p class="cr-help">{l s='Counted from the last change to the cart. Reminders stop as soon as the customer orders or unsubscribes. "Code" adds the discount code below to that reminder.' d='Modules.Cartrecovery.Admin'}</p>
            </div>

            <div class="cr-field">
              <label for="CARTREC_AUDIENCE">{l s='Who receives reminders' d='Modules.Cartrecovery.Admin'}</label>
              <select class="form-control" id="CARTREC_AUDIENCE" name="CARTREC_AUDIENCE">
                <option value="all"{if $cr.cfg.audience == 'all'} selected{/if}>{l s='All customers and guests' d='Modules.Cartrecovery.Admin'}</option>
                <option value="newsletter"{if $cr.cfg.audience == 'newsletter'} selected{/if}>{l s='Newsletter subscribers only' d='Modules.Cartrecovery.Admin'}</option>
                <option value="optin"{if $cr.cfg.audience == 'optin'} selected{/if}>{l s='Customers who accepted partner offers only' d='Modules.Cartrecovery.Admin'}</option>
              </select>
              <p class="cr-help">{l s='Applies to automatic sending. Check what the law of your country requires.' d='Modules.Cartrecovery.Admin'}</p>
            </div>

            <div class="cr-grid2">
              <div class="cr-field">
                <label for="CARTREC_MAXAGE">{l s='Ignore carts older than' d='Modules.Cartrecovery.Admin'}</label>
                <div class="input-group cr-num">
                  <input class="form-control" type="number" min="1" id="CARTREC_MAXAGE" name="CARTREC_MAXAGE" value="{$cr.cfg.maxage|intval}">
                  <span class="input-group-addon">{l s='days' d='Modules.Cartrecovery.Admin'}</span>
                </div>
              </div>
              <div class="cr-field">
                <label for="CARTREC_MINAMOUNT">{l s='Minimum cart' d='Modules.Cartrecovery.Admin'}</label>
                <div class="input-group cr-num">
                  <input class="form-control" type="number" min="0" step="0.01" id="CARTREC_MINAMOUNT" name="CARTREC_MINAMOUNT" value="{$cr.cfg.minamount|floatval}">
                  <span class="input-group-addon">{$cr.currency_sign|escape:'html':'UTF-8'}</span>
                </div>
              </div>
            </div>

            <div class="cr-field">
              <label for="CARTREC_BCC">{l s='Blind copy to (optional)' d='Modules.Cartrecovery.Admin'}</label>
              <input class="form-control" type="email" id="CARTREC_BCC" name="CARTREC_BCC" value="{$cr.cfg.bcc|escape:'html':'UTF-8'}" placeholder="you@example.com">
            </div>

            <div class="cr-field">
              <span class="cr-label" id="cr-track-label">{l s='Count opens and clicks' d='Modules.Cartrecovery.Admin'}</span>
              <span class="switch prestashop-switch fixed-width-lg" role="radiogroup" aria-labelledby="cr-track-label">
                <input type="radio" name="CARTREC_TRACK" id="CARTREC_TRACK_on" value="1"{if $cr.cfg.track} checked="checked"{/if}>
                <label for="CARTREC_TRACK_on">{l s='Yes' d='Modules.Cartrecovery.Admin'}</label>
                <input type="radio" name="CARTREC_TRACK" id="CARTREC_TRACK_off" value="0"{if !$cr.cfg.track} checked="checked"{/if}>
                <label for="CARTREC_TRACK_off">{l s='No' d='Modules.Cartrecovery.Admin'}</label>
                <a class="slide-button btn"></a>
              </span>
              <p class="cr-help">{l s='Adds an invisible image and passes the cart link through your shop. Opens are approximate: some mail apps load images for privacy, others block them.' d='Modules.Cartrecovery.Admin'}</p>
            </div>

            <div class="cr-cron">
              <span class="cr-label">{l s='Cron URL' d='Modules.Cartrecovery.Admin'}</span>
              <div class="cr-cron__row">
                <input class="form-control cr-cron__url" type="text" readonly id="cr-cron-url" value="{$cr.cron_url|escape:'html':'UTF-8'}" aria-label="{l s='Cron URL' d='Modules.Cartrecovery.Admin'}">
                <button type="button" class="btn btn-default cr-btn cr-btn--icon" data-copy-target="#cr-cron-url" aria-label="{l s='Copy the URL' d='Modules.Cartrecovery.Admin'}"><i class="material-icons" aria-hidden="true">content_copy</i></button>
              </div>
              <p class="cr-help">{l s='Call it every hour from your hosting panel. Each shop of a multistore has its own URL.' d='Modules.Cartrecovery.Admin'}</p>
              <p class="cr-lastrun">{l s='Last run:' d='Modules.Cartrecovery.Admin'}
                {if $cr.last_run_date}
                  <strong>{$cr.last_run_date|escape:'html':'UTF-8'}</strong>
                  {if $cr.last_run.enabled} · {l s='%n% sent' sprintf=['%n%' => $cr.last_run.sent|intval] d='Modules.Cartrecovery.Admin'}{if $cr.last_run.errors} · {l s='%n% errors' sprintf=['%n%' => $cr.last_run.errors|intval] d='Modules.Cartrecovery.Admin'}{/if}{else} · {l s='automatic sending is off' d='Modules.Cartrecovery.Admin'}{/if}
                {else}
                  <strong>{l s='never' d='Modules.Cartrecovery.Admin'}</strong>
                {/if}
              </p>
            </div>
          </div>
        </section>

        <section class="cr-card">
          <h3 class="cr-card__head cr-card__head--split"><span><i class="material-icons" aria-hidden="true">local_offer</i> {l s='Discount code' d='Modules.Cartrecovery.Admin'}</span>
            <span class="switch prestashop-switch fixed-width-md" role="radiogroup" aria-label="{l s='Discount code' d='Modules.Cartrecovery.Admin'}">
              <input type="radio" name="CARTREC_DISC" id="CARTREC_DISC_on" value="1"{if $cr.cfg.disc} checked="checked"{/if}>
              <label for="CARTREC_DISC_on">{l s='On' d='Modules.Cartrecovery.Admin'}</label>
              <input type="radio" name="CARTREC_DISC" id="CARTREC_DISC_off" value="0"{if !$cr.cfg.disc} checked="checked"{/if}>
              <label for="CARTREC_DISC_off">{l s='Off' d='Modules.Cartrecovery.Admin'}</label>
              <a class="slide-button btn"></a>
            </span>
          </h3>
          <div class="cr-card__body">
            <div class="cr-field">
              <span class="cr-label" id="cr-dtype-label">{l s='Type' d='Modules.Cartrecovery.Admin'}</span>
              <div class="cr-pills" role="radiogroup" aria-labelledby="cr-dtype-label">
                <label class="cr-pill cr-pill--radio"><input type="radio" name="CARTREC_DISC_TYPE" value="percent"{if $cr.cfg.disc_type == 'percent'} checked{/if}> {l s='Percentage' d='Modules.Cartrecovery.Admin'}</label>
                <label class="cr-pill cr-pill--radio"><input type="radio" name="CARTREC_DISC_TYPE" value="amount"{if $cr.cfg.disc_type == 'amount'} checked{/if}> {l s='Fixed amount' d='Modules.Cartrecovery.Admin'}</label>
                <label class="cr-pill cr-pill--radio"><input type="radio" name="CARTREC_DISC_TYPE" value="shipping"{if $cr.cfg.disc_type == 'shipping'} checked{/if}> {l s='Free shipping' d='Modules.Cartrecovery.Admin'}</label>
              </div>
            </div>
            <div class="cr-grid2">
              <div class="cr-field">
                <label for="CARTREC_DISC_VALUE">{l s='Value' d='Modules.Cartrecovery.Admin'}</label>
                <div class="input-group cr-num">
                  <input class="form-control" type="number" min="0" step="0.01" id="CARTREC_DISC_VALUE" name="CARTREC_DISC_VALUE" value="{$cr.cfg.disc_value|floatval}">
                  <span class="input-group-addon" id="cr-disc-unit" data-percent="%" data-amount="{$cr.currency_sign|escape:'html':'UTF-8'}" data-shipping="—">{if $cr.cfg.disc_type == 'amount'}{$cr.currency_sign|escape:'html':'UTF-8'}{elseif $cr.cfg.disc_type == 'shipping'}—{else}%{/if}</span>
                </div>
              </div>
              <div class="cr-field">
                <label for="CARTREC_DISC_DAYS">{l s='Valid for' d='Modules.Cartrecovery.Admin'}</label>
                <div class="input-group cr-num">
                  <input class="form-control" type="number" min="1" id="CARTREC_DISC_DAYS" name="CARTREC_DISC_DAYS" value="{$cr.cfg.disc_days|intval}">
                  <span class="input-group-addon">{l s='days' d='Modules.Cartrecovery.Admin'}</span>
                </div>
              </div>
              <div class="cr-field">
                <label for="CARTREC_DISC_MIN">{l s='Minimum cart' d='Modules.Cartrecovery.Admin'}</label>
                <div class="input-group cr-num">
                  <input class="form-control" type="number" min="0" step="0.01" id="CARTREC_DISC_MIN" name="CARTREC_DISC_MIN" value="{$cr.cfg.disc_min|floatval}">
                  <span class="input-group-addon">{$cr.currency_sign|escape:'html':'UTF-8'}</span>
                </div>
              </div>
              <div class="cr-field">
                <label for="CARTREC_DISC_PREFIX">{l s='Code prefix' d='Modules.Cartrecovery.Admin'}</label>
                <input class="form-control cr-upper" type="text" id="CARTREC_DISC_PREFIX" name="CARTREC_DISC_PREFIX" value="{$cr.cfg.disc_prefix|escape:'html':'UTF-8'}" maxlength="12">
              </div>
            </div>
            <label class="cr-check-label"><input type="hidden" name="CARTREC_DISC_APPLY" value="0"><input type="checkbox" name="CARTREC_DISC_APPLY" value="1"{if $cr.cfg.disc_apply} checked{/if}> {l s='Put the code in the customer cart, so it is already applied when they come back' d='Modules.Cartrecovery.Admin'}</label>
            <div class="cr-note">
              {l s='Example:' d='Modules.Cartrecovery.Admin'} <strong class="cr-mono">{if $cr.cfg.disc_prefix}{$cr.cfg.disc_prefix|escape:'html':'UTF-8'}-{/if}7KQ2M9XP</strong>
              <div class="cr-help">{l s='One code per customer and cart, usable once, only by that customer. Later reminders repeat the same code. Unused codes are deleted a day after they expire.' d='Modules.Cartrecovery.Admin'}</div>
            </div>
          </div>
        </section>

        <section class="cr-card">
          <h3 class="cr-card__head"><i class="material-icons" aria-hidden="true">unsubscribe</i> {l s='Unsubscribes' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <p class="cr-p">{l s='Every reminder has an unsubscribe link and a one-click unsubscribe header (Gmail, Apple Mail).' d='Modules.Cartrecovery.Admin'}
              {l s='%n% customers unsubscribed; they never get reminders, manual ones included.' sprintf=['%n%' => $cr.unsub_count|intval] d='Modules.Cartrecovery.Admin'}</p>
          </div>
        </section>
      </div>
    </div>

    <div class="cr-savebar">
      <button type="submit" name="submitCrSettings" value="1" class="btn btn-primary cr-btn"><i class="material-icons" aria-hidden="true">check</i> {l s='Save' d='Modules.Cartrecovery.Admin'}</button>
    </div>
  </form>

  <script>
    var iso = '{$cr.iso|escape:'javascript':'UTF-8'}';
    var pathCSS = '{$smarty.const._THEME_CSS_DIR_|escape:'javascript':'UTF-8'}';
    var ad = '';
    window.crLangNames = {ldelim}{foreach from=$cr.languages item=lg name=ln}"{$lg.id|intval}": "{$lg.name|escape:'javascript':'UTF-8'}"{if !$smarty.foreach.ln.last}, {/if}{/foreach}{rdelim};
    $(function () {
      tinySetup({
        editor_selector: 'cr-rte',
        height: 320,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        setup: function (ed) { ed.on('init', function () { if (window.crEditorReady) { window.crEditorReady(); } }); }
      });
    });
  </script>

{elseif $cr.tab == 'design'}
  {* ================================================================= APPEARANCE *}
  <form method="post" enctype="multipart/form-data" action="{$cr.urls.design|escape:'html':'UTF-8'}" id="cr-design" class="cr-form cr-live">
    <input type="hidden" name="cr_lang" id="cr-lang" value="{$cr.id_lang|intval}">
    <div class="cr-cols">
      <div class="cr-cols__main cr-stackcol">
        <section class="cr-card">
          <h3 class="cr-card__head"><i class="material-icons" aria-hidden="true">dashboard</i> {l s='E-mail design' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <div class="cr-tpls" role="radiogroup" aria-label="{l s='E-mail design' d='Modules.Cartrecovery.Admin'}">
              {foreach from=$cr.templates key=code item=t}
                <label class="cr-tpl{if $cr.look.tpl == $code} is-active{/if}" data-accent="{$t.accent|escape:'html':'UTF-8'}" data-header="{$t.header|escape:'html':'UTF-8'}">
                  <input type="radio" class="cr-tpl__radio" name="CARTREC_TPL" value="{$code|escape:'html':'UTF-8'}"{if $cr.look.tpl == $code} checked{/if}>
                  <span class="cr-tpl__thumb cr-tpl__thumb--{$code|escape:'html':'UTF-8'}" aria-hidden="true" style="--a:{if $cr.look.tpl == $code}{$cr.look.accent|escape:'html':'UTF-8'}{else}{$t.accent|escape:'html':'UTF-8'}{/if};--h:{if $cr.look.tpl == $code}{$cr.look.header|escape:'html':'UTF-8'}{else}{$t.header|escape:'html':'UTF-8'}{/if};">
                    <span class="cr-tpl__paper">
                      <span class="cr-tpl__head"></span><span class="cr-tpl__hero"></span><span class="cr-tpl__title"></span>
                      <span class="cr-tpl__line"></span><span class="cr-tpl__line cr-tpl__line--short"></span><span class="cr-tpl__box"></span><span class="cr-tpl__btn"></span>
                    </span>
                  </span>
                  <span class="cr-tpl__row">
                    <span class="cr-tpl__name">{$t.name|escape:'html':'UTF-8'}</span>
                    <span class="cr-tpl__badge"><i class="material-icons" aria-hidden="true">check</i> {l s='Selected' d='Modules.Cartrecovery.Admin'}</span>
                  </span>
                  <span class="cr-tpl__desc">{$t.desc|escape:'html':'UTF-8'}</span>
                </label>
              {/foreach}
            </div>
          </div>
        </section>

        <section class="cr-card">
          <h3 class="cr-card__head"><i class="material-icons" aria-hidden="true">palette</i> {l s='Customisation' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body cr-grid2">
            <div class="cr-field">
              <label for="CARTREC_ACCENT">{l s='Main colour' d='Modules.Cartrecovery.Admin'}</label>
              <div class="cr-color">
                <input type="color" class="cr-color__pick" data-for="CARTREC_ACCENT" value="{$cr.look.accent|escape:'html':'UTF-8'}" aria-label="{l s='Pick the main colour' d='Modules.Cartrecovery.Admin'}">
                <input class="form-control cr-color__hex" type="text" id="CARTREC_ACCENT" name="CARTREC_ACCENT" value="{$cr.look.accent|escape:'html':'UTF-8'}" maxlength="7">
              </div>
              <p class="cr-help">{l s='Button, links and accents.' d='Modules.Cartrecovery.Admin'}</p>
            </div>
            <div class="cr-field">
              <label for="CARTREC_HEADER">{l s='Header colour' d='Modules.Cartrecovery.Admin'}</label>
              <div class="cr-color">
                <input type="color" class="cr-color__pick" data-for="CARTREC_HEADER" value="{$cr.look.header|escape:'html':'UTF-8'}" aria-label="{l s='Pick the header colour' d='Modules.Cartrecovery.Admin'}">
                <input class="form-control cr-color__hex" type="text" id="CARTREC_HEADER" name="CARTREC_HEADER" value="{$cr.look.header|escape:'html':'UTF-8'}" maxlength="7">
              </div>
              <p class="cr-help">{l s='Logo band and footer. The text turns white or black by itself.' d='Modules.Cartrecovery.Admin'}</p>
            </div>
            <div class="cr-field">
              <span class="cr-label" id="cr-reas-label">{l s='Reassurance strip' d='Modules.Cartrecovery.Admin'}</span>
              <span class="switch prestashop-switch fixed-width-lg" role="radiogroup" aria-labelledby="cr-reas-label">
                <input type="radio" name="CARTREC_REAS" id="CARTREC_REAS_on" value="1"{if $cr.look.reas} checked="checked"{/if}>
                <label for="CARTREC_REAS_on">{l s='Yes' d='Modules.Cartrecovery.Admin'}</label>
                <input type="radio" name="CARTREC_REAS" id="CARTREC_REAS_off" value="0"{if !$cr.look.reas} checked="checked"{/if}>
                <label for="CARTREC_REAS_off">{l s='No' d='Modules.Cartrecovery.Admin'}</label>
                <a class="slide-button btn"></a>
              </span>
            </div>
            <div class="cr-field">
              <span class="cr-label" id="cr-dlang-label">{l s='Language of the texts below' d='Modules.Cartrecovery.Admin'}</span>
              <div class="cr-pills" role="radiogroup" aria-labelledby="cr-dlang-label">
                {foreach from=$cr.languages item=lg}
                  <button type="button" role="radio" class="cr-pill{if $lg.id == $cr.id_lang} is-active{/if}" data-lang="{$lg.id|intval}" aria-checked="{if $lg.id == $cr.id_lang}true{else}false{/if}" title="{$lg.name|escape:'html':'UTF-8'}">{$lg.iso|escape:'html':'UTF-8'}</button>
                {/foreach}
              </div>
            </div>
            {foreach from=$cr.languages item=lg}
              <div class="cr-langpane cr-span2 cr-grid2" data-lang-pane="{$lg.id|intval}"{if $lg.id != $cr.id_lang} hidden{/if}>
                <div class="cr-field">
                  <label for="CARTREC_BTN_{$lg.id|intval}">{l s='Button text' d='Modules.Cartrecovery.Admin'} ({$lg.iso|escape:'html':'UTF-8'})</label>
                  <input class="form-control" type="text" id="CARTREC_BTN_{$lg.id|intval}" name="CARTREC_BTN[{$lg.id|intval}]" value="{$cr.shared.btn[$lg.id]|escape:'html':'UTF-8'}" maxlength="60">
                </div>
                <div class="cr-field">
                  <label for="CARTREC_TAGLINE_{$lg.id|intval}">{l s='Tagline next to the shop name (optional)' d='Modules.Cartrecovery.Admin'}</label>
                  <input class="form-control" type="text" id="CARTREC_TAGLINE_{$lg.id|intval}" name="CARTREC_TAGLINE[{$lg.id|intval}]" value="{$cr.shared.tagline[$lg.id]|escape:'html':'UTF-8'}" maxlength="80">
                </div>
                <div class="cr-field">
                  <label for="CARTREC_REAS_TEXT_{$lg.id|intval}">{l s='Reassurance strip' d='Modules.Cartrecovery.Admin'}</label>
                  <textarea class="form-control" id="CARTREC_REAS_TEXT_{$lg.id|intval}" name="CARTREC_REAS_TEXT[{$lg.id|intval}]" rows="3">{$cr.shared.reas_text[$lg.id]|escape:'html':'UTF-8'}</textarea>
                  <p class="cr-help">{l s='Up to 3 lines: title | short text.' d='Modules.Cartrecovery.Admin'}</p>
                </div>
                <div class="cr-field">
                  <label for="CARTREC_FOOTER_{$lg.id|intval}">{l s='Footer text' d='Modules.Cartrecovery.Admin'}</label>
                  <textarea class="form-control" id="CARTREC_FOOTER_{$lg.id|intval}" name="CARTREC_FOOTER[{$lg.id|intval}]" rows="3">{$cr.shared.footer[$lg.id]|escape:'html':'UTF-8'}</textarea>
                </div>
              </div>
            {/foreach}
            <div class="cr-field cr-span2">
              <label for="CARTREC_HERO">{l s='Banner image' d='Modules.Cartrecovery.Admin'} <span class="cr-muted">({l s='Showcase design' d='Modules.Cartrecovery.Admin'})</span></label>
              <div class="cr-hero">
                <img src="{$cr.look.hero|escape:'html':'UTF-8'}" alt="{l s='Current banner' d='Modules.Cartrecovery.Admin'}" class="cr-hero__img">
                <div class="cr-hero__ctrl">
                  <input type="file" id="CARTREC_HERO" name="CARTREC_HERO" accept="image/jpeg,image/png,image/gif,image/webp">
                  {if $cr.has_custom_hero}
                    <label class="cr-check-label"><input type="checkbox" name="CARTREC_HERO_RM" value="1"> {l s='Remove the image and use the default one' d='Modules.Cartrecovery.Admin'}</label>
                  {/if}
                </div>
              </div>
              <p class="cr-help">{l s='1200 × 576 px recommended (JPG, 4 MB max).' d='Modules.Cartrecovery.Admin'}</p>
            </div>
          </div>
          <div class="cr-card__foot">
            <button type="button" class="btn btn-default cr-btn" id="cr-reset-colors">{l s='Default colours of the design' d='Modules.Cartrecovery.Admin'}</button>
            <button type="submit" name="submitCrDesign" value="1" class="btn btn-primary cr-btn"><i class="material-icons" aria-hidden="true">check</i> {l s='Save' d='Modules.Cartrecovery.Admin'}</button>
          </div>
        </section>
      </div>

      <aside class="cr-cols__preview cr-card cr-preview" aria-label="{l s='Live preview' d='Modules.Cartrecovery.Admin'}">
        <div class="cr-card__head cr-preview__head">
          <span><i class="material-icons" aria-hidden="true">visibility</i> {l s='Live preview' d='Modules.Cartrecovery.Admin'}</span>
          <span class="cr-seg" role="group" aria-label="{l s='Screen size' d='Modules.Cartrecovery.Admin'}">
            <button type="button" class="cr-seg__btn is-active" data-device="desktop" aria-pressed="true">{l s='Desktop' d='Modules.Cartrecovery.Admin'}</button>
            <button type="button" class="cr-seg__btn" data-device="mobile" aria-pressed="false">{l s='Mobile' d='Modules.Cartrecovery.Admin'}</button>
          </span>
        </div>
        <div class="cr-preview__stage" id="cr-preview-stage">
          <div class="cr-preview__scaler" id="cr-preview-scaler">
            <iframe id="cr-preview-frame" title="{l s='E-mail preview' d='Modules.Cartrecovery.Admin'}" sandbox="allow-same-origin" scrolling="no"></iframe>
          </div>
          <div class="cr-preview__loading">{l s='Updating…' d='Modules.Cartrecovery.Admin'}</div>
        </div>
        <div class="cr-preview__foot">
          <button type="submit" name="submitCrPreview" value="1" formtarget="_blank" class="btn btn-link cr-btn cr-btn--sm"><i class="material-icons" aria-hidden="true">open_in_new</i> {l s='Open full screen' d='Modules.Cartrecovery.Admin'}</button>
          <span class="cr-help">{l s='Example: reminder 1 with the latest abandoned cart. The message is edited in the Settings tab.' d='Modules.Cartrecovery.Admin'}</span>
        </div>
      </aside>
    </div>
  </form>

{elseif $cr.tab == 'compose'}
  {* ================================================================= ONE-OFF REMINDER *}
  {if !$cr.compose.carts}
    <div class="cr-card cr-empty">{l s='No abandoned cart to remind in the last 90 days.' d='Modules.Cartrecovery.Admin'}</div>
  {else}
  <form method="post" action="{$cr.urls.compose|escape:'html':'UTF-8'}" id="cr-compose" class="cr-form cr-live" data-rte="1">
    <div class="cr-cols">
      <div class="cr-cols__main cr-stackcol">
        <section class="cr-card">
          <h3 class="cr-card__head"><span class="cr-stepnum">1</span> {l s='Customer and cart' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <div class="cr-field">
              <label for="cr_cart">{l s='Cart to remind' d='Modules.Cartrecovery.Admin'}</label>
              <select class="form-control" id="cr_cart" name="cr_cart" data-reload="{$cr.urls.compose|escape:'html':'UTF-8'}">
                {foreach from=$cr.compose.carts item=o}
                  <option value="{$o.id_cart|intval}"{if $o.id_cart == $cr.compose.selected} selected{/if}
                    data-name="{$o.name|escape:'html':'UTF-8'}" data-first="{$o.firstname|escape:'html':'UTF-8'}"
                    data-info="{$o.email|escape:'html':'UTF-8'} · #{$o.id_cart|intval} · {$o.lang|escape:'html':'UTF-8'} · {$o.total|escape:'html':'UTF-8'} · {$o.date|escape:'html':'UTF-8'}"
                    data-status="{$o.status|escape:'html':'UTF-8'}" data-sent="{$o.sent|intval}" data-img="{$o.img|escape:'html':'UTF-8'}">{$o.name|escape:'html':'UTF-8'} — {$o.email|escape:'html':'UTF-8'} — #{$o.id_cart|intval} — {$o.total|escape:'html':'UTF-8'}</option>
                {/foreach}
              </select>
            </div>
            <div class="cr-recipient">
              <img src="" alt="" id="cr-rc-img" class="cr-recipient__img">
              <div class="cr-recipient__txt">
                <div class="cr-strong" id="cr-rc-name"></div>
                <div class="cr-muted" id="cr-rc-info"></div>
              </div>
              <span class="cr-badge" id="cr-rc-status"></span>
            </div>
            <p class="cr-help">{l s='The texts below are reminder %n% in the language of this cart. Choosing another cart reloads them.' sprintf=['%n%' => $cr.compose.step|intval] d='Modules.Cartrecovery.Admin'}</p>
          </div>
        </section>

        <section class="cr-card">
          <h3 class="cr-card__head"><span class="cr-stepnum">2</span> {l s='Design' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <div class="cr-tpls cr-tpls--compact" role="radiogroup" aria-label="{l s='E-mail design' d='Modules.Cartrecovery.Admin'}">
              {foreach from=$cr.templates key=code item=t}
                <label class="cr-tpl{if $cr.compose.look.tpl == $code} is-active{/if}" data-accent="{$t.accent|escape:'html':'UTF-8'}" data-header="{$t.header|escape:'html':'UTF-8'}">
                  <input type="radio" class="cr-tpl__radio" name="CARTREC_TPL" value="{$code|escape:'html':'UTF-8'}"{if $cr.compose.look.tpl == $code} checked{/if}>
                  <span class="cr-tpl__thumb cr-tpl__thumb--{$code|escape:'html':'UTF-8'}" aria-hidden="true" style="--a:{if $cr.compose.look.tpl == $code}{$cr.compose.look.accent|escape:'html':'UTF-8'}{else}{$t.accent|escape:'html':'UTF-8'}{/if};--h:{if $cr.compose.look.tpl == $code}{$cr.compose.look.header|escape:'html':'UTF-8'}{else}{$t.header|escape:'html':'UTF-8'}{/if};">
                    <span class="cr-tpl__paper"><span class="cr-tpl__head"></span><span class="cr-tpl__hero"></span><span class="cr-tpl__title"></span><span class="cr-tpl__line"></span><span class="cr-tpl__box"></span><span class="cr-tpl__btn"></span></span>
                  </span>
                  <span class="cr-tpl__row"><span class="cr-tpl__name">{$t.name|escape:'html':'UTF-8'}</span></span>
                </label>
              {/foreach}
            </div>
            <div class="cr-grid2">
              <div class="cr-field">
                <label for="CARTREC_ACCENT">{l s='Main colour' d='Modules.Cartrecovery.Admin'}</label>
                <div class="cr-color">
                  <input type="color" class="cr-color__pick" data-for="CARTREC_ACCENT" value="{$cr.compose.look.accent|escape:'html':'UTF-8'}" aria-label="{l s='Pick the main colour' d='Modules.Cartrecovery.Admin'}">
                  <input class="form-control cr-color__hex" type="text" id="CARTREC_ACCENT" name="CARTREC_ACCENT" value="{$cr.compose.look.accent|escape:'html':'UTF-8'}" maxlength="7">
                </div>
                <input type="hidden" id="CARTREC_HEADER" name="CARTREC_HEADER" value="{$cr.compose.look.header|escape:'html':'UTF-8'}">
              </div>
              <div class="cr-field">
                <label for="CARTREC_BTN">{l s='Button text' d='Modules.Cartrecovery.Admin'}</label>
                <input class="form-control" type="text" id="CARTREC_BTN" name="CARTREC_BTN" value="{$cr.compose.look.button|escape:'html':'UTF-8'}" maxlength="60">
              </div>
            </div>
            <label class="cr-check-label"><input type="hidden" name="CR_ONEOFF_DISC" value="0"><input type="checkbox" name="CR_ONEOFF_DISC" value="1"{if $cr.compose.look.discount.on} checked{/if}{if !$cr.cfg.disc} disabled{/if}> {l s='Include the discount code' d='Modules.Cartrecovery.Admin'}{if !$cr.cfg.disc} <span class="cr-muted">({l s='turn on the discount code in Settings first' d='Modules.Cartrecovery.Admin'})</span>{/if}</label>
            <p class="cr-help">{l s='These choices apply to this e-mail only; your saved settings do not change.' d='Modules.Cartrecovery.Admin'}</p>
          </div>
        </section>

        <section class="cr-card">
          <h3 class="cr-card__head"><span class="cr-stepnum">3</span> {l s='Message' d='Modules.Cartrecovery.Admin'}</h3>
          <div class="cr-card__body">
            <div class="cr-field">
              <label for="CR_ONEOFF_SUBJECT">{l s='Subject' d='Modules.Cartrecovery.Admin'}</label>
              <input class="form-control" type="text" id="CR_ONEOFF_SUBJECT" name="CR_ONEOFF_SUBJECT" value="{$cr.compose.look.subject|escape:'html':'UTF-8'}" required>
            </div>
            <div class="cr-field">
              <label for="CR_ONEOFF_TITLE">{l s='Title in the e-mail' d='Modules.Cartrecovery.Admin'}</label>
              <input class="form-control" type="text" id="CR_ONEOFF_TITLE" name="CR_ONEOFF_TITLE" value="{$cr.compose.look.title|escape:'html':'UTF-8'}">
            </div>
            <div class="cr-field">
              <label for="CR_ONEOFF_BODY">{l s='Message' d='Modules.Cartrecovery.Admin'}</label>
              <textarea class="cr-rte" id="CR_ONEOFF_BODY" name="CR_ONEOFF_BODY" rows="12">{$cr.compose.look.body|escape:'html':'UTF-8'}</textarea>
            </div>
            <div class="cr-vars">
              <div class="cr-vars__title">{l s='Variables' d='Modules.Cartrecovery.Admin'} <span class="cr-help">({l s='click to insert' d='Modules.Cartrecovery.Admin'})</span></div>
              <div class="cr-vars__grid">
                {foreach from=$cr.vars key=code item=label}
                  <div><button type="button" class="cr-chip" data-copy="{$code|escape:'html':'UTF-8'}">{$code|escape:'html':'UTF-8'}</button> <span>{$label|escape:'html':'UTF-8'}</span></div>
                {/foreach}
              </div>
            </div>
          </div>
        </section>

        <div class="cr-sendbar">
          <div class="cr-sendbar__test">
            <div class="cr-field">
              <label for="CARTREC_TEST_EMAIL">{l s='Test to' d='Modules.Cartrecovery.Admin'}</label>
              <input class="form-control" type="email" id="CARTREC_TEST_EMAIL" name="CARTREC_TEST_EMAIL" value="{$cr.cfg.test_email|escape:'html':'UTF-8'}">
            </div>
            <button type="submit" name="submitCrComposeTest" value="1" class="btn btn-default cr-btn cr-btn--outline"><i class="material-icons" aria-hidden="true">science</i> {l s='Send the test' d='Modules.Cartrecovery.Admin'}</button>
          </div>
          <button type="submit" name="submitCrCompose" value="1" class="btn btn-primary cr-btn cr-btn--lg" id="cr-compose-send" data-confirm="{l s='Send this e-mail to %name%?' d='Modules.Cartrecovery.Admin'}">
            <i class="material-icons" aria-hidden="true">send</i> {l s='Send to' d='Modules.Cartrecovery.Admin'} <span id="cr-rc-first"></span>
          </button>
        </div>
      </div>

      <aside class="cr-cols__preview cr-card cr-preview" aria-label="{l s='Live preview' d='Modules.Cartrecovery.Admin'}">
        <div class="cr-card__head cr-preview__head">
          <span><i class="material-icons" aria-hidden="true">visibility</i> {l s='Live preview' d='Modules.Cartrecovery.Admin'}</span>
          <span class="cr-seg" role="group" aria-label="{l s='Screen size' d='Modules.Cartrecovery.Admin'}">
            <button type="button" class="cr-seg__btn is-active" data-device="desktop" aria-pressed="true">{l s='Desktop' d='Modules.Cartrecovery.Admin'}</button>
            <button type="button" class="cr-seg__btn" data-device="mobile" aria-pressed="false">{l s='Mobile' d='Modules.Cartrecovery.Admin'}</button>
          </span>
        </div>
        <div class="cr-preview__stage" id="cr-preview-stage">
          <div class="cr-preview__scaler" id="cr-preview-scaler">
            <iframe id="cr-preview-frame" title="{l s='E-mail preview' d='Modules.Cartrecovery.Admin'}" sandbox="allow-same-origin" scrolling="no"></iframe>
          </div>
          <div class="cr-preview__loading">{l s='Updating…' d='Modules.Cartrecovery.Admin'}</div>
        </div>
        <div class="cr-preview__foot">
          <span class="cr-help">{l s='Preview with the real cart of the customer. In the preview and the test, links open the cart page without logging anyone in.' d='Modules.Cartrecovery.Admin'}</span>
        </div>
      </aside>
    </div>
  </form>

  <script>
    var iso = '{$cr.iso|escape:'javascript':'UTF-8'}';
    var pathCSS = '{$smarty.const._THEME_CSS_DIR_|escape:'javascript':'UTF-8'}';
    var ad = '';
    $(function () {
      tinySetup({
        editor_selector: 'cr-rte',
        height: 300,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        setup: function (ed) {
          ed.on('keyup change undo redo', function () { if (window.crSchedulePreview) { window.crSchedulePreview(); } });
        }
      });
    });
  </script>
  {/if}

{else}
  {* ================================================================= ABANDONED CARTS *}
  <form method="get" action="{$cr.filter_form.action|escape:'html':'UTF-8'}" class="cr-card cr-filters">
    {foreach from=$cr.filter_form.fields key=fk item=fv}
      <input type="hidden" name="{$fk|escape:'html':'UTF-8'}" value="{$fv|escape:'html':'UTF-8'}">
    {/foreach}
    <div class="cr-field">
      <label for="cr_status">{l s='Status' d='Modules.Cartrecovery.Admin'}</label>
      <select class="form-control" id="cr_status" name="cr_status">
        <option value="all"{if $cr.filters.status == 'all'} selected{/if}>{l s='All' d='Modules.Cartrecovery.Admin'}</option>
        <option value="none"{if $cr.filters.status == 'none'} selected{/if}>{l s='Not reminded' d='Modules.Cartrecovery.Admin'}</option>
        <option value="sent"{if $cr.filters.status == 'sent'} selected{/if}>{l s='Reminded' d='Modules.Cartrecovery.Admin'}</option>
        <option value="won"{if $cr.filters.status == 'won'} selected{/if}>{l s='Recovered' d='Modules.Cartrecovery.Admin'}</option>
        <option value="unsub"{if $cr.filters.status == 'unsub'} selected{/if}>{l s='Unsubscribed' d='Modules.Cartrecovery.Admin'}</option>
      </select>
    </div>
    <div class="cr-field">
      <label for="cr_days">{l s='Period' d='Modules.Cartrecovery.Admin'}</label>
      <select class="form-control" id="cr_days" name="cr_days">
        <option value="7"{if $cr.filters.days == 7} selected{/if}>{l s='Last 7 days' d='Modules.Cartrecovery.Admin'}</option>
        <option value="30"{if $cr.filters.days == 30} selected{/if}>{l s='Last 30 days' d='Modules.Cartrecovery.Admin'}</option>
        <option value="90"{if $cr.filters.days == 90} selected{/if}>{l s='Last 90 days' d='Modules.Cartrecovery.Admin'}</option>
      </select>
    </div>
    <div class="cr-field cr-filters__search">
      <label for="cr_q">{l s='Customer' d='Modules.Cartrecovery.Admin'}</label>
      <input class="form-control" type="search" id="cr_q" name="cr_q" value="{$cr.filters.q|escape:'html':'UTF-8'}" placeholder="{l s='Name, e-mail or cart number' d='Modules.Cartrecovery.Admin'}">
    </div>
    <button type="submit" class="btn btn-default cr-btn">{l s='Filter' d='Modules.Cartrecovery.Admin'}</button>
  </form>

  <form method="post" action="{$cr.urls.page|escape:'html':'UTF-8'}&amp;cr_page={$cr.filters.page|intval}" id="cr-bulk-form">
    <div class="cr-bulk">
      <span>{l s='Selected carts:' d='Modules.Cartrecovery.Admin'} <strong id="cr-selected">0</strong></span>
      <button type="submit" name="submitCrBulk" value="1" class="btn cr-btn cr-btn--light" id="cr-bulk-btn" disabled data-confirm="{l s='Send the next reminder to the selected carts?' d='Modules.Cartrecovery.Admin'}">
        <i class="material-icons" aria-hidden="true">send</i> {l s='Send the reminder to the selection' d='Modules.Cartrecovery.Admin'}
      </button>
    </div>

    <div class="cr-card cr-tablewrap">
      {if $cr.carts}
      <table class="cr-table">
        <thead>
          <tr>
            <th class="cr-table__check"><input type="checkbox" id="cr-check-all" aria-label="{l s='Select all' d='Modules.Cartrecovery.Admin'}"></th>
            <th>{l s='Cart' d='Modules.Cartrecovery.Admin'}</th>
            <th>{l s='Customer' d='Modules.Cartrecovery.Admin'}</th>
            <th>{l s='Products' d='Modules.Cartrecovery.Admin'}</th>
            <th class="text-right">{l s='Total' d='Modules.Cartrecovery.Admin'}</th>
            <th>{l s='Last activity' d='Modules.Cartrecovery.Admin'}</th>
            <th>{l s='Status' d='Modules.Cartrecovery.Admin'}</th>
            <th class="text-right">{l s='Actions' d='Modules.Cartrecovery.Admin'}</th>
          </tr>
        </thead>
        <tbody>
        {foreach from=$cr.carts item=c}
          <tr>
            <td class="cr-table__check">
              {if $c.status != 'won' && !$c.unsubscribed}<input type="checkbox" name="cr_carts[]" value="{$c.id_cart|intval}" class="cr-check" aria-label="{l s='Select cart %id%' sprintf=['%id%' => $c.id_cart|intval] d='Modules.Cartrecovery.Admin'}">{/if}
            </td>
            <td><a href="{$c.cart_admin_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">#{$c.id_cart|intval}</a></td>
            <td>
              <div class="cr-strong">{$c.firstname|escape:'html':'UTF-8'} {$c.lastname|escape:'html':'UTF-8'}{if $c.is_guest} <span class="cr-tag">{l s='guest' d='Modules.Cartrecovery.Admin'}</span>{/if}</div>
              <div class="cr-muted">{$c.email|escape:'html':'UTF-8'} · {$c.lang_iso|escape:'html':'UTF-8'}</div>
            </td>
            <td>
              <div class="cr-prod">
                {if $c.product_img}<img src="{$c.product_img|escape:'html':'UTF-8'}" alt="" loading="lazy">{else}<span class="cr-prod__ph"></span>{/if}
                <div class="cr-prod__txt">
                  <div class="cr-prod__name" title="{$c.product_name|escape:'html':'UTF-8'}">{$c.product_name|escape:'html':'UTF-8'}</div>
                  <div class="cr-muted">{l s='%n% items' sprintf=['%n%' => $c.count|intval] d='Modules.Cartrecovery.Admin'}</div>
                </div>
              </div>
            </td>
            <td class="text-right cr-strong cr-nowrap">{$c.total_display|escape:'html':'UTF-8'}</td>
            <td class="cr-nowrap">{$c.date_display|escape:'html':'UTF-8'}</td>
            <td>
              {if $c.unsubscribed && $c.status != 'won'}
                <span class="cr-badge cr-badge--unsub">{l s='Unsubscribed' d='Modules.Cartrecovery.Admin'}</span>
              {elseif $c.status == 'none'}
                <span class="cr-badge">{l s='Not reminded' d='Modules.Cartrecovery.Admin'}</span>
              {elseif $c.status == 'sent'}
                <span class="cr-badge cr-badge--sent">{l s='Reminder %n% of %max% sent · %date%' sprintf=['%n%' => $c.last_step|intval, '%max%' => $cr.max_step|intval, '%date%' => $c.sent_display] d='Modules.Cartrecovery.Admin'}</span>
                <div class="cr-muted cr-small">{if $c.mode == 'auto'}{l s='automatic' d='Modules.Cartrecovery.Admin'}{elseif $c.mode == 'custom'}{l s='one-off' d='Modules.Cartrecovery.Admin'}{else}{l s='manual' d='Modules.Cartrecovery.Admin'}{/if}{if $c.sent_count > $c.last_step} · {l s='sent %n% times' sprintf=['%n%' => $c.sent_count|intval] d='Modules.Cartrecovery.Admin'}{/if}</div>
              {else}
                <span class="cr-badge cr-badge--won">{l s='Recovered · order #%id%' sprintf=['%id%' => $c.id_order|intval] d='Modules.Cartrecovery.Admin'}</span>
              {/if}
              {if $c.discount_code}<div class="cr-muted cr-small">{l s='Code' d='Modules.Cartrecovery.Admin'} <code>{$c.discount_code|escape:'html':'UTF-8'}</code></div>{/if}
              {if $c.status != 'none'}<div class="cr-muted cr-small">{if $c.date_click}{l s='Opened · clicked' d='Modules.Cartrecovery.Admin'}{elseif $c.date_open}{l s='Opened, not clicked' d='Modules.Cartrecovery.Admin'}{else}{l s='Not opened yet' d='Modules.Cartrecovery.Admin'}{/if}</div>{/if}
            </td>
            <td class="text-right cr-nowrap">
              {if $c.status == 'won'}
                <a class="btn btn-default cr-btn cr-btn--sm" href="{$c.order_admin_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='View order' d='Modules.Cartrecovery.Admin'}</a>
              {elseif $c.unsubscribed}
                <button type="button" class="btn btn-default cr-btn cr-btn--sm" disabled>{l s='Unsubscribed' d='Modules.Cartrecovery.Admin'}</button>
              {else}
                <a class="btn btn-default cr-btn cr-btn--sm cr-btn--icon-sm" href="{$c.compose_url|escape:'html':'UTF-8'}" title="{l s='One-off reminder' d='Modules.Cartrecovery.Admin'}" aria-label="{l s='One-off reminder for cart %id%' sprintf=['%id%' => $c.id_cart|intval] d='Modules.Cartrecovery.Admin'}"><i class="material-icons" aria-hidden="true">edit</i></a>
                {if $c.status == 'none'}
                  <a class="btn btn-primary cr-btn cr-btn--sm" href="{$c.send_url|escape:'html':'UTF-8'}">{l s='Remind' d='Modules.Cartrecovery.Admin'}</a>
                {elseif $c.is_resend}
                  <a class="btn btn-default cr-btn cr-btn--sm" href="{$c.send_url|escape:'html':'UTF-8'}" data-confirm="{l s='Send the last reminder again to this customer?' d='Modules.Cartrecovery.Admin'}">{l s='Send again' d='Modules.Cartrecovery.Admin'}</a>
                {else}
                  <a class="btn btn-default cr-btn cr-btn--sm" href="{$c.send_url|escape:'html':'UTF-8'}">{l s='Send reminder %n%' sprintf=['%n%' => $c.next_step|intval] d='Modules.Cartrecovery.Admin'}</a>
                {/if}
              {/if}
            </td>
          </tr>
        {/foreach}
        </tbody>
      </table>
      {else}
        <div class="cr-empty">{l s='No cart matches these filters.' d='Modules.Cartrecovery.Admin'}</div>
      {/if}
    </div>
  </form>

  {if $cr.total > 0}
  <div class="cr-pager">
    <span>{l s='%n% carts' sprintf=['%n%' => $cr.total|intval] d='Modules.Cartrecovery.Admin'}</span>
    {if $cr.pages > 1}
    <nav aria-label="{l s='Pagination' d='Modules.Cartrecovery.Admin'}" class="cr-pager__nav">
      {for $p=1 to $cr.pages}
        <a href="{$cr.urls.page|escape:'html':'UTF-8'}&amp;cr_page={$p}" class="cr-pager__link{if $p == $cr.filters.page} is-active{/if}"{if $p == $cr.filters.page} aria-current="page"{/if}>{$p}</a>
      {/for}
    </nav>
    {/if}
  </div>
  {/if}
{/if}
</div>

{if $status == 'ok'}
  <p>
    {l s='Your order on %s is complete.' sprintf=[$shop_name] d='Modules.Decta.Shop'}
    <br><br>
    {l s='You have chosen the cash on delivery method.' d='Modules.Decta.Shop'}
    <br><br><span>{l s='Your order will be sent very soon.' d='Modules.Decta.Shop'}</span>
    <br><br>{l s='For any questions or for further information, please contact our' d='Modules.Decta.Shop'} <a href="{$contact_url}">{l s='customer support' d='Modules.Decta.Shop'}</a>.
  </p>
{else}
  <p class="warning">
    {l s='We noticed a problem with your order. If you think this is an error, feel free to contact our [1]expert customer support team[/1].' d='Modules.Decta.Shop' sprintf=['[1]' => "<a href='{$contact_url}'>", '[/1]' => '</a>']}
  </p>
{/if}
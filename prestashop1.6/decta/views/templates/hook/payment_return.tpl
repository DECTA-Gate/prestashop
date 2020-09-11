{if $status == 'ok'}
<p>
	{l s='Payment received, your order is complete.' sprintf=$shop_name mod='decta'}
</p>
{else}
	<p class="warning">
		{l s='We noticed a problem with your order. If you think this is an error, feel free to contact our' mod='decta'}
		<a href="{$link->getPageLink('contact', true)|escape:'html'}">{l s='expert customer support team' mod='decta'}</a>.
	</p>
{/if}

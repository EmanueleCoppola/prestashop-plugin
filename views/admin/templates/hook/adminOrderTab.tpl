<div class="card mt-2">
    <div class="card-header">
        <h3 class="card-header-title">
            {l s='Satispay Refunds (%d)' mod='satispay' sprintf=[count($refunds)]}
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-12">
            {if !empty($refunds)}
                <table class="table">
                    <thead>
                        <tr>
                            <th>{l s='Refund date' mod='satispay'}</th>
                            <th>{l s='Refund ID' mod='satispay'}</th>
                            <th>{l s='Refund amount' mod='satispay'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$refunds item=refund}
                            <tr>
                                <td>{dateFormat date=$refund->date_add full=1}</td>
                                <td>{$refund->refund_id}</td>
                                <td>{displayPrice price=$refund->getAmount() currency=$order->id_currency}</td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            {/if}

            {if !empty($success_message)}
                <div>
                    <div class="alert alert-success" role="alert">
                        {$success_message}
                    </div>
                </div>
            {/if}

            {if !empty($error_message)}
                <div>
                    <div class="alert alert-danger" role="alert">
                        {$error_message}
                    </div>
                </div>
            {/if}

            {if !$order_refunded}
                <form id="satispay-refund-form" action="{$satispay_refund_url}" method="post" class="form-horizontal">
                    <input type="hidden" name="satispay_refund_id_order" value="{$order->id}">

                    <div class="form-group row">
                        <label class="col-sm-3 col-form-label">{l s='Refund amount' mod='satispay'}</label>

                        <div class="col-sm-9 d-flex">
                            <div class="flex-grow-1">
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0.01" name="satispay_refund_amount" class="form-control" required>

                                    <div class="input-group-append">
                                        <span class="input-group-text">{$currency->sign}</span>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary ml-2 flex-1" name="satispay_refund">
                                {l s='Process refund' mod='satispay'}
                            </button>
                        </div>
                    </div>
                </form>
            {/if}

            </div>
        </div>
    </div>
</div>

{literal}
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        document
            .getElementById('satispay-refund-form')
            .addEventListener('submit', function(e) {
                if (!confirm('{/literal}{l s='Are you sure you want to process this refund?' mod='satispay'}{literal}')) {
                    e.preventDefault();
                }
            });
    });
</script>
{/literal}

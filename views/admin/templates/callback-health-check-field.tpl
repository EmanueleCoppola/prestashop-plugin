<div class="help-block callback-status {$status}">
    <div class="callback-status-circle" title="{$status}"></div>
    <div class="callback-status-message todo">{l s='The callback health check has not been executed yet, configure the plugin first.' mod='satispay'}</div>
    <div class="callback-status-message pending">{l s='The callback health check is executing.' mod='satispay'}</div>
    <div class="callback-status-message success">{l s='The callback health check is succeded and the system is working properly.' mod='satispay'}</div>
    <div class="callback-status-message fail">{l s='The callback health check failed and the callback system is not working properly.' mod='satispay'}</div>
</div>

<div class="callback-status {$status}">
    <div class="callback-status-message pending">
        {l s='The callback healthcheck is being executed, please refresh this page in a while.' mod='satispay'}
    </div>

    <div class="callback-status-message fail">
        {l s='Your Prestashop installation is currently facing issues receiving the server-to-server callback.' mod='satispay'}
        <br><br>
        {l s='Please verify that your network firewall or your application firewall (e.g. ModSecurity) are not blocking the comunication with the Satispay servers.' mod='satispay'}
        <br><br>
        {l s='For more information read <a href="https://developers.satispay.com/reference/callback-s2s" target="_blank">here</a>.' mod='satispay'}
    </div>
</div>

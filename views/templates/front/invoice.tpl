{*
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<style>
    #edit-panel-background, #edit-panel-container
    {
        display: none;
        width:100%;
        height: 100%;
        top: 0;
        left: 0;
        position: fixed;
        z-index: 9999999;
        background: transparent;
    }
    #edit-panel-background
    {
        background-color: #bbbbbb;
        z-index: 9999998;
        opacity: 0.7;
    }
    #edit-panel-container div
    {
        width: 500px;
        height: 150px;
        position: fixed;
        top: 50%;
        left: 50%;
        /* bring your own prefixes */
        transform: translate(-50%, -50%);
        opacity: 1;
    }
</style>

<div id='edit-panel-background'></div>
<div id="edit-panel-container">
    <div class="edit-panel-content panel">
        <input type="hidden" id="id_document" value=''>
        <table class='table table-data-sheet'>
            <tbody>
                <tr>
                    <td class='fixed-width-sm'><label>{l s='Number' mod='mpfixinvoice'}</label></td>
                    <td class='fixed-width-md'><input class="input form-control" id="edit-number-invoice" value="0"></td>
                    <td class='fixed-width-sm'><label>{l s='Date' mod='mpfixinvoice'}</label></td>
                    <td class='fixed-width-md'><input class="input input-date form-control" id="edit-date-invoice" value=""></td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right;">
                        <a class="btn btn-default" onclick="javascript:ajaxCallEditSelectedDocument($('#id_document').val());">
                            <i class='icon icon-pencil-square'></i>
                            {l s='Edit' mod='mpfixinvoice'}
                        </a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>  
                    
<script>
    var id_document = '';
    $(document).ready(function(){
        $(window).load(function(){
            formatDisplay();
            $('#edit-panel-container').on('show', function(){
                
            });
            $('#edit-panel-container').on('hide', function(){
                $('#edit-panel-background').fateOut();
            });
        });
        
        
        $('.input-date').datepicker({
                prevText: '',
                nextText: '',
                dateFormat: 'yy-mm-dd',
                // Define a custom regional settings in order to use PrestaShop translation tools
                currentText: 'Adesso',
                closeText: 'Fatto'
        });
    });
    function fixInvoice(id_order)
    {
        event.stopPropagation();
        event.preventDefault();
        
        if (!confirm('{l s='Reindex this invoice?' mod='mpfixinvoice'}')) {
            return false;
        }
        
        $.ajax({
                type: 'POST',
                //dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'FixInvoice',
                    id_order: id_order,
                    invoice_number: $("#input_invoice_number").val(),
                    invoice_date: $("#input_invoice_date").val()
                }
        })
        .done(function(result){
            if (result) {
                jAlert(result);
            } else {
                jAlert("{l s='Operation done.' mod='mpfixinvoice'}");
            }
        })
        .fail(function(){
            jAlert("{l s='Error during updating.' mod='mpfixinvoices'}", '{l s='Error' mod='mpfixinvoice'}');
        });
    }
    function delInvoice(id_order)
    {
        event.stopPropagation();
        event.preventDefault();
        
        if (!confirm('{l s='Delete this invoice?' mod='mpfixinvoice'}')) {
            return false;
        }
        
        $.ajax({
                type: 'POST',
                //dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'DelInvoice',
                    id_order: id_order
                }
        })
        .done(function(result){
            if (result) {
                jAlert(result);
            } else {
                $('a[data-selenium-id="view_invoice"]').remove();
                jAlert("{l s='Operation done.' mod='mpfixinvoice'}");
            }
        })
        .fail(function(){
            jAlert("{l s='Error during deletion.' mod='mpfixinvoice'}", '{l s='Error' mod='mpfixinvoice'}');
        });
    }
    function fixPayment(id_order)
    {
        event.stopPropagation();
        event.preventDefault();
        
        if (!confirm('{l s='Fix payments for this order?' mod='mpfixinvoice'}')) {
            return false;
        }
        
        $.ajax({
                type: 'POST',
                //dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'DelPayment',
                    id_order: id_order
                }
        })
        .done(function(result){
            if (result) {
                jAlert(result);
            } else {
                jAlert("{l s='Operation done.' mod='mpfixinvoice'}");
            }
        })
        .fail(function(){
            jAlert("{l s='Error during deletion.' mod='mpfixinvoice'}", '{l s='Error' mod='mpfixinvoice'}');
        });
    }
    
    /**
    * Place elements in adminOrderPage
    * @returns { bool } True if success, False otherwise     */
    function formatDisplay()
    {
        var addPaymentTab = $('#formAddPaymentPanel');
        var thead_tr = $("#documents_table > thead >tr:nth-child(1)");
        var tbody_tr = $("#documents_table > tbody >tr");
        
        $(thead_tr).find('th:last').remove();
        $(thead_tr).append(getPaymentColumn(true));
        $(tbody_tr).each(function(){
            $(this).find('td:last').remove();
            $(this).append(getPaymentColumn());
            id_document = String($(this).attr('id'));
            /** Fix document number display **/
            ajaxCallFixNumberDocumentDisplay(this);
        });
        addFixPaymentButton();
    }
    
    function addFixPaymentButton()
    {
        $('#formAddPaymentPanel table tbody tr').each(function(){
            if(!$(this).hasClass('payment_information') && !$(this).hasClass('current-edit')) {
                var i_delete = $('<i></i>')
                    .addClass('icon')
                    .addClass('icon-times')
                    .css('color', '#BB5555');
                var a_delete = $('<a></a>')
                    .addClass('btn')
                    .addClass('btn-default')
                    .append(i_delete)
                    .attr('onclick', 'javascript:actionSelectedTableRowPayment(this, "delete");');
                var i_default = $('<i></i>')
                    .addClass('icon')
                    .addClass('icon-star')
                    .css('color', '#5555BB');
                var a_default = $('<a></a>')
                    .addClass('btn')
                    .addClass('btn-default')
                    .append(i_default)
                    .attr('onclick', 'javascript:actionSelectedTableRowPayment(this, "default");');
                var td = $('<td></td>').append(a_default).append(a_delete);
                $(this).append('<td>'+$(td).html()+'</td>');
                $(this).closest('thead').find('tr:nth-child(1)').find('th:nth-child(1)').css('width', '96px');
            }
        });
    }
    function ajaxCallFixNumberDocumentDisplay(tr)
    {
        $.ajax({
                type: 'POST',
                dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'getDocumentNumber',
                    id_document: id_document
                }
        })
        .done(function(result){
            if (!result.error) {
                console.log('document number:', result.number);
                $(tr).find('td:nth-child(3)').find('a').html(result.number);
                $(tr).find('td:nth-child(4)').text(result.total);
            }
        })
        .fail(function(){
            console.log('Error display correct document number:', id_document);
        });
    }
    
    function getPaymentColumn(title=false)
    {
        if (title) {
            var span = $('<span></span>').addClass('title_box').html('{l s='Actions' mod='mpfixinvoice'}');
            var th = $('<th></th>').html(span);
            var HTML = '<th>'+$(th).html()+'</th>';
        } else {
            var i_delete = $('<i></i>')
                .addClass('icon')
                .addClass('icon-trash')
                .css(
                    {
                        "color": '#993333',
                        "margin-right": "8px"
                    }
                );
            var a_delete = $('<a></a>')
                .addClass('btn')
                .addClass('btn-default')
                .append(i_delete)
                .append('{l s='Delete' mod='mpfixinvoice'}')
                .attr('onclick', 'javascript:actionSelectedTableRow(this, "delete");')
                .css(
                    {
                        "margin-right": "8px"
                    }
                );
            var i_edit = $('<i></i>')
                .addClass('icon')
                .addClass('icon-pencil')
                .css(
                    {
                        "color": '#333399',
                        "margin-right": "8px"
                });
            var a_edit = $('<a></a>')
                .addClass('btn')
                .addClass('btn-default')
                .append(i_edit)
                .append('{l s='Edit' mod='mpfixinvoice'}')
                .attr('onclick', 'javascript:actionSelectedTableRow(this, "edit");');
        
            var td = $('<td></td>').append(a_edit).append(a_delete);
            var HTML = "<td>"+$(td).html()+"</td>";
        }
        return HTML;
    }
    
    function actionSelectedTableRow(item, action)
    {
        var row = $(item).closest('tr');
        var document = String($(row).find('td:nth-child(3)').text()).trim();

        if (action==="delete") {
            var message = "{l s='Delete selected document?' mod='mpfixinvoice'}";
        } else if(action==="edit") {
            var message = "{l s='Edit selected document?' mod='mpfixinvoice'}";
        }
        jConfirm(message, document, function(r){
            if (!r) {
                return false;
            }
            var id_document = String(id_document = $(item).closest('tr').attr('id')).trim();
            if (action==="delete") {
                ajaxCallDeleteSelectedDocument(id_document);
            } else if (action==="edit") {
                $('#id_document').val(id_document);
                $('#edit-panel-container').fadeIn(function(){
                    $('#edit-panel-background').fadeIn();
                });
            }
        });
    }
    
    function ajaxCallDeleteSelectedDocument(id_document)
    {
        $.ajax({
                type: 'POST',
                dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'deleteSelectedDocument',
                    id_document: id_document
                }
        })
        .done(function(result){
            if (result.error) {
                $.growl.error({ size: "large", title: result.title, message: result.message });
            } else {
                $.growl.notice({ size: "large", title: result.title, message: result.message });
            }
        })
        .fail(function(){
            jAlert("{l s='Error during deletion.' mod='mpfixinvoice'}", '{l s='Error' mod='mpfixinvoice'}');
        });
    }
    
    function ajaxCallEditSelectedDocument(id_document)
    {
        $('#edit-panel-container').fadeOut(function(){
            $('#edit-panel-background').fadeOut();
        });
        
        $.ajax({
                type: 'POST',
                dataType: 'json',
                url: '{$ajax_url|escape:'htmlall':'UTF-8'}',
                useDefaultXhrHeader: false,
                data: 
                {
                    token: '{$token|escape:'htmlall':'UTF-8'}',
                    ajax: true,
                    action: 'editSelectedDocument',
                    id_document: id_document,
                    number_document: $('#edit-number-invoice').val(),
                    date_document: $('#edit-date-invoice').val()
                }
        })
        .done(function(result){
            if (result.error) {
                $.growl.error({ size: "large", title: result.title, message: result.message });
            } else {
                $.growl.notice({ size: "large", title: result.title, message: result.message });
            }
        })
        .fail(function(){
            jAlert("{l s='Error during deletion.' mod='mpfixinvoice'}", '{l s='Error' mod='mpfixinvoice'}');
        });
    }
</script>
            

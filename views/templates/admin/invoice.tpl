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
    #edit-panel-background,
    #edit-panel-container,
    #add-panel-background,
    #add-panel-container
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
<div id="add-panel-container">
    <div class="add-panel-content panel">
        <input type="hidden" id="id_document" value=''>
        <table class='table table-data-sheet'>
            <tbody>
                <tr>
                    <td class='fixed-width-sm'><label>{l s='Number' mod='mpfixinvoice'}</label></td>
                    <td class='fixed-width-md'><input class="input form-control" id="add-number-invoice" value="0"></td>
                    <td class='fixed-width-sm'><label>{l s='Date' mod='mpfixinvoice'}</label></td>
                    <td class='fixed-width-md'><input class="input input-date form-control" id="add-date-invoice" value=""></td>
                </tr>
                <tr>
                    <td colspan="5" style="text-align: right;">
                        <a class="btn btn-default" onclick="javascript:ajaxCallAddSelectedDocument($('#id_document').val());">
                            <i class='icon icon-pencil-square'></i>
                            {l s='Add' mod='mpfixinvoice'}
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
        /** After page is ready **/
        $(window).load(function(){
            formatDisplay();
        });
        /** set datepicker **/
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
    
    /**
     *  
     * @param String id_order Order id <type>_<number>
     * @returns Boolean True if success, false otherwise     
     **/
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
    * @returns bool True if success, False otherwise     */
    function formatDisplay()
    {
        fixTableDocuments();
        fixTablePayment();
        return true;
    }
    
    function fixTableDocuments()
    {
        //var addPaymentTab = $('#formAddPaymentPanel');
        var thead_tr = $("#documents_table > thead >tr:nth-child(1)");
        var tbody_tr = $("#documents_table > tbody >tr");
        
        $(thead_tr).find('th:last').remove();
        $(thead_tr).append(addDocumentColumn(true));
        $(tbody_tr).each(function(){
            $(this).find('td:last').remove();
            $(this).append(addDocumentColumn());
            id_document = String($(this).attr('id'));
            /** Fix document number display **/
            ajaxCallFixNumberDocumentDisplay(this);
        });
    }
    
    function fixTablePayment()
    {
        addFixPaymentButton();
        
        var table = $('#formAddPayment table');
        $(table).css('overflow-x', 'visible');
        /** Fix table width **/
        var titles = $(table).find('thead').find('tr:first');
        $(titles).find('th:nth-child(1)').css('width', '98px');
        $(titles).find('th:nth-child(2)').css('width', '150px');
        $(titles).find('th:nth-child(3)').css('width', '100px');
    }
    
    /**
    * Add buttons to Payment Table 
    * @returns bool True if success, False otherwise
    **/
    function addFixPaymentButton()
    {
        $('#formAddPayment table thead tr:first th:last').remove();
        $('#formAddPayment table thead tr:first').append(addPaymentColumn(true));
        $('#formAddPayment table tbody tr.current-edit').remove();
        $('#formAddPayment table tbody tr').each(function(){
            $(this).find('td:last').remove();
            if(!$(this).hasClass('payment_information') && !$(this).hasClass('current-edit')) {
                var i_delete = createIcon('icon-times', '#BB5555');
                var a_delete = createLink(i_delete, 'javascript:actionSelectedTableRowPayment(this, "delete");');
                        
                var i_default = createIcon('icon-star', '#5555BB');
                var a_default = createLink(i_default, 'javascript:actionSelectedTableRowPayment(this, "default");');
                
                var i_add = createIcon('icon-plus', '#55BB55');
                var a_add = createLink(i_add, 'javascript:actionSelectedTableRowPayment(this, "add");');
                        
                var td = $('<td></td>').append(a_add).append(a_default).append(a_delete);
                $(this).append('<td>'+$(td).html()+'</td>');
            }
        });
        return true;
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
    
    function createIcon(icon_class, color, text='')
    {
        var html = '';
        var icon = $('<i></i>')
            .addClass('icon')
            .addClass(icon_class)
            .css(
                {
                    "color": color
                }
            );
        if (!text || 0 !== text.length) {
            var span = $('<span></span').append(icon).append(text);
            html = $('<div></div>').append(span).html();
        } else {
            html = $('<div></div>').append(icon).html();
        }
        return html;
    }
    
    function createLink(icon, onclick)
    {
        var html = '';
        var a = $('<a></a>')
                .addClass('btn')
                .addClass('btn-default')
                .append(icon)
                .attr('onclick', onclick)
                .css(
                    {
                        "margin-right": "8px"
                    }
                );
        html = $('<div></div>').append(a).html();
        console.log('createLink: ', html);
        return html;
    }
    
    function addDocumentColumn(title=false)
    {
        if (title) {
            var span = $('<span></span>').addClass('title_box').html('{l s='Actions' mod='mpfixinvoice'}');
            var th = $('<th></th>').append(span);
            var HTML = '<th>'+$(th).html()+'</th>';
        } else {
            var i_delete = createIcon('icon-trash', '#993333', '{l s='Delete' mod='mpfixinvoice'}');
            var a_delete = createLink(i_delete, 'javascript:actionSelectedTableRowDocument(this, "delete");');   
            
            var i_edit = createIcon('icon-pencil', '#333399', '{l s='Edit' mod='mpfixinvoice'}');
            var a_edit = createLink(i_edit, 'javascript:actionSelectedTableRowDocument(this, "edit");');
            
            var td = $('<td></td>').append(a_edit).append(a_delete);
            var HTML = "<td>"+$(td).html()+"</td>";
        }
        return HTML;
    }
    
    function addPaymentColumn(title=false)
    {
        
    }
    
    function actionSelectedTableRowDocument(item, action)
    {
        var row = $(item).closest('tr');
        var document = '{l s='Document ' mod='mpfixinvoice'}'
            + String($(row).find('td:nth-child(3)').text()).trim();

        if (action==="delete") {
            var message = "{l s='Delete selected document?' mod='mpfixinvoice'}";
        } else if(action==="edit") {
            var message = "{l s='Edit selected document?' mod='mpfixinvoice'}";
        } else if(action==="add") {
            var message = "{l s='Add a new payment?' mod='mpfixinvoice'}";
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
            } else if (action==="add") {
                $('#id_document').val(id_document);
                $('#add-panel-container').fadeIn(function(){
                    $('#add-panel-background').fadeIn();
                });
            }
        });
    }
    
    function actionSelectedTableRowPayment(item, action)
    {
        var row = $(item).closest('tr');
        var document = String($(row).find('td:nth-child(3)').text()).trim();

        if (action==="delete") {
            var message = "{l s='Delete selected payment?' mod='mpfixinvoice'}";
        } else if(action==="default") {
            var message = "{l s='Set this payment as default?' mod='mpfixinvoice'}";
        } else if(action==="add") {
            var message = "{l s='Add a new payment?' mod='mpfixinvoice'}";
        }
        jConfirm(message, document, function(r){
            if (!r) {
                return false;
            }
            var id_document = String(id_document = $(item).closest('tr').attr('id')).trim();
            if (action==="delete") {
                ajaxCallDeleteSelectedPayment(id_document);
            } else if (action==="default") {
                ajaxCallDefaultSelectedPayment(id_document);
            } else if (action==="add") {
                $('#id_document').val(id_document);
                $('#add-panel-container').fadeIn(function(){
                    $('#add-panel-background').fadeIn();
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
    
    function ajaxCallAddSelectedDocument(id_document)
    {
        $('#add-panel-container').fadeOut(function(){
            $('#add-panel-background').fadeOut();
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
            

/**
 *
 * Javascript file for the ProcessWire Module Multi Sites (Process)
 *
 * @author Francis Otieno (Kongondo)
 *
 * Copyright (C) 2017 by Francis Otieno
 *
 */

/**
 * Get all time zones and their values to populate autocomplete.
 *
 * We get the values from a JS Object that was set in MultisitesRender::renderTimeZonesScript.
 * This is used by timeZonesAutocomplete() in single edit variations congiguration.
 *
 * @return Array timeZones Contains indexed array of time zones and their respective values.
 *
*/
function getTimezones() {
	var timeZones = {};
	// check if time zones values object is loaded
	if (typeof multiSitesTimeZonesConfig == 'object') timeZones = multiSitesTimeZonesConfig;
	return timeZones;
}

/**
 * Autocomplete of ALL available time zones and their values.
 *
 * Autocomplete built by jQueryUI Autocomplete.
 * We also use the 'select event' to populate the 'select attribute values' select with attribute values as options.
 * This is used in single edit variations congiguration.
 *
 * @param Object i The input element we are applying Autocomplete on.
 *
*/
function timeZonesAutocomplete(i) {
	var k = getTimezones();
	$(i).autocomplete({
		source: k,
		delay: 0,// @note: low ok since fetching locally
		//minLength: , @note: just in case needed in the future
		select: function (event, ui) {// @note: called on select value in autocomplete
            var selectedTimeZoneID = $.inArray(ui.item.value, k);
            $('input#ms_timezone_id').val(selectedTimeZoneID)
		}, 
		response: function(event, ui) {// @note: check if content found
            // ui.content is the array that's about to be sent to the response callback.
            if (ui.content.length === 0) {
				//noTimeZoneFoundNotice();// @todo?	
            } 

            /*else {
                $("#empty-message").empty();
            }*/
        }
	});

}

/**
 * Initiate a magnific instance on a given element.
 * 
 * @param Object elem The element with data about initiating the magnific popup.
 *
 */
function initPopup(elem) {
	
	var src;// magnific content source
    var title;// popup title bar
    var popupID;// div to popup
    
    // inline popups    
    popupID = elem.attr('data-popup-src');
    src = 'div#' + popupID;
    type = 'inline';
    title = elem.attr('data-popup-title');
    closeMarkup = getcloseMarkup(title);

	
	// @note: we need to open magnific directly like this in this case otherwise requires double click on init item!
	// @see: https://stackoverflow.com/questions/22653524/magnific-popup-requires-clicking-twice-to-open-image-slider
	$.magnificPopup.open({
		items: [{
				src: src,
				type: type
		}],
		callbacks: {
			close: function () {

			},
			open: function () {
                var magnific = this;
                // cancel button
                $('div#ms_installed_sites_action_confirm').on('click', 'button#ms_installed_sites_action_cancel_btn', function (e) {
                    clearDeleteList();
                    magnific.close();
                })

			}// END open: callback
		},
		closeMarkup: closeMarkup,		
		mainClass: 'ms_popups',
		//alignTop: true,
		//enableEscapeKey: false,
		showCloseBtn: true,
		closeOnBgClick: false,
	})
}

/**
 * Apply css class for background color to required inputs in a form.
 * 
 * @param Object i Input and select elements to add/remove css clas to/from for background colour.
 *
 */
function requiredBackgroundColor(i) {    
    // apply background color to empty required inputs
    i.filter(function () {
        return $.trim(this.value) === '';
    }).addClass('ms_required');
    // detect input changes on required fields and remove class for background-color if input not empty
    i.on('input', function() {
        if($(this).val() == '') $(this).addClass('ms_required')
        else $(this).removeClass('ms_required')
    });

}

/**
 * Build title for magnific modal
 * 
 * @param String title Title to display for the modal.
 * @return String out Markup of title.
 *
 */
function getcloseMarkup(title) {
    var out = title ? '<div id="ms_popups_close" class="NoticeError ui-helper-clearfix"><span id="ms_popup_title">' + title + '</span><span class="mfp-close">&#215;</span></div>' : '';
    return out;
}

// ##  VALIDATORS ## 

/**
 * 
 * 
 * @param {any} form 
 * @returns 
 */
function validateForm(form) {    
    var valid = {};// JS Object
    valid = {
        errors: 0,
        notices: []
    };    
    valid = validateRequieredFields(form, valid);
    valid = validateAdminName(form, valid);
    valid = validateSuperUser(form, valid);
    return valid;
}

/**
 * 
 * 
 * @param {any} form 
 * @param {any} valid 
 * @returns 
 */
function validateRequieredFields(form, valid) {
    var inputs = form.find('input, select, textarea#ms_create_copy_paste').not('input#ms_timezone_id,input#ms_confirm');
    inputs.each(function () {
        var i = ($(this));
        var val = i.val();
        var hiddenParent = i.closest('div.ms_hide');
        if (hiddenParent.length) return;
        if (0 == val) {
            valid.errors = 1;
            return false;
        }
    });
    var notice = $('p#ms_required_fields').text();
    valid.notices.push(notice);
    return valid;
}

/**
 * 
 * 
 * @param {any} form 
 * @param {any} valid 
 * @returns 
 */
function validateAdminName(form, valid) {

    var admin = form.find('input#ms_admin_url');
    var hiddenParent = admin.closest('div.ms_hide');
    if(hiddenParent.length) return valid;
    //var adminName = form.find('input#ms_admin_url').val();
    var adminName = admin.val();

    // admin name disallowed ('wire' and 'site')
    if (adminName == 'wire' || adminName == 'site') {
        valid.errors = 1;
        var notice = $('p#ms_admin_name_disallowed').text();
        valid.notices.push(notice);
    }

    // admin name too short [shoudl be at least 2 char]
    if (admin.length && adminName.length < 2) {
        valid.errors = 1;
        var notice = $('p#ms_admin_name_short').text();
        valid.notices.push(notice);
    }

    // admin name disallowed characters [a-z 0-9]
    if (!validateCharacters(adminName)) {
        valid.errors = 1;
        var notice = $('p#ms_admin_name_characters_disallowed').text();
        valid.notices.push(notice);
    }

    return valid;
}

/**
 * 
 * 
 * @param {any} form 
 * @param {any} valid 
 * @returns 
 */
function validateSuperUser(form, valid) {

    var name = form.find('input#ms_superuser_name');
    var password = form.find('input#ms_superuser_pass');
    var passwordConfirm = form.find('input#ms_superuser_pass_confirm');
    var email = form.find('input#ms_superuser_email');

    var superUserName = name.val();
    var superUserPassword = password.val();
    var superUserPasswordConfirm = passwordConfirm.val();
    var superUserEmail = email.val();

    var hiddenParentName = name.closest('div.ms_hide');    
    if (!hiddenParentName.length) {
        // superuser name disallowed characters [a-z 0-9]
        if (!validateCharacters(superUserName)) {
            valid.errors = 1;
            var notice = $('p#ms_superuser_name_characters_disallowed').text();
            valid.notices.push(notice);
        }
        // superuser name too short
        if (superUserName.length < 2) {
            valid.errors = 1;
            var notice = $('p#ms_superuser_name_short').text();
            valid.notices.push(notice);
        } 
    }
    
    var hiddenParentPassword = password.closest('div.ms_hide');
    if (!hiddenParentPassword.length) { 
        // superuser passwords mismatch
        if (superUserPassword !== superUserPasswordConfirm) {
            valid.errors = 1;
            var notice = $('p#ms_superuser_passwords_mismatch').text();
            valid.notices.push(notice);
        }
        // superuser password too short [should be at least 6 char]
        if (password.length && superUserPassword.length < 6) {
            valid.errors = 1;
            var notice = $('p#ms_superuser_password_short').text();
            valid.notices.push(notice);
        }
    }
    
    var hiddenParentEmail = email.closest('div.ms_hide');
    if (!hiddenParentEmail.length) { 
        // superuser email invalid
        if (!validateEmail(superUserEmail)) {
            valid.errors = 1;
            var notice = $('p#ms_superuser_email_invalid').text();
            valid.notices.push(notice);
        }
    }

    return valid;
    
}
    
/**
 * `
 * 
 * @param {any} email 
 * @returns
 *
 */
function validateEmail(email) {
    var emailReg = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
    return emailReg.test( email );
}

/**
 * Validate a-z 0-9 characters only in a string.
 * 
 * @param String string String to be validated.
 * @returns Bool true|false Whether string validated.
 *
 */
function validateCharacters(string) {
    var stringReg = /^[a-z0-9]+$/; 
    return stringReg.test( string );
}

/**
 * Find all selected items for actioning
 * 
 */
function getSelectedItems() {
    var selectedItems = $('input[name="ms_items_action_selected[]"]:checked');    
	return selectedItems;
}

/**
 * 
 * 
 * @param object selectedItems Collection of checked items to action.
 * @param integer mode Whether to deleting files or databases.
 */
function buildDeleteList(selectedItems, mode) {
    var deleteFilesList = getDeleteList(1);
    var deleteDatabasesList = getDeleteList(2);
    var dataSrc
    var id;
    // delete files mode
    if (1 == mode) {
        id = 'ms_delete_sites_files_confirm';
        buildDeleteListHeader(id,deleteFilesList);
        dataSrc = 'data-directory';
        appendDeleteList(selectedItems, dataSrc, deleteFilesList);
    }
    // delete databases mode
    else if (2 == mode) {
        id = 'ms_delete_sites_databases_confirm';
        buildDeleteListHeader(id,deleteDatabasesList);
        dataSrc = 'data-database';
        appendDeleteList(selectedItems, dataSrc, deleteDatabasesList);
    }

}

/**
 * 
 * 
 * @param object items Collection of items from which to find data to append as notices.
 * @param string dataSrc The data attribute to get text with details of items that will be actioned.
 * @param object list The element to append notices to.
 */
function appendDeleteList(items,dataSrc,list) {
    var appendStr = '';
    $.each(items, function () {
        var p = $(this).parent();
        var text = p.attr(dataSrc);
        appendStr += '<li>' + text + '</li>';        
    });
    list.append(appendStr);
}

/**
 * Build and insert markup to use as a header in a delete list.
 * 
 * @param string id The ID of the paragraph with text to use as a popup message.
 * @param object list The element to append the header to.
 *
 */
function buildDeleteListHeader(id,list) {
    var popupMessage = $('p#'+id).text();
    $('<p class="ms_confirm_list">' + popupMessage + '</p>').insertBefore(list); 
}

/**
 * Get the delete list to append items to.
 *
 * These are <ol> lists </ol> for delete files and databases confirmation.
 * 
 * @param integer mode Whether to get the files or datbases list.
 * @returns object list The relevant list.
 *
 */
function getDeleteList(mode) {
    var list;
    if (1 == mode) list = $('ol#ms_installed_sites_delete_files_confirm_list');
    else list = $('ol#ms_installed_sites_delete_databases_confirm_list');
    return list;
}

/**
 * Uncheck items that were to be actioned before cancellation of action.
 * 
 */
function clearDeleteList() {
    var selectedItems = getSelectedItems();
    $('input#ms_toggle_all').prop("checked", false);
    $.each(selectedItems, function () {this.checked = false;});
    $('select#ms_items_action_select').val('select');
}

/**
 * spinner for UX.
 *
 * This is used in single edit variations congiguration.
 *
 * @param Object i Spinner <i> element to show/hide.
 * @param String mode Whether to show or hide i element.
 *
 */
function spinner(i, mode){				
	if(!i.length) return;				
	if(mode == 'in') i.removeClass('ms_hide');
	else {
        setTimeout(function(){
            $(i).addClass('ms_hide');
        },700)
	}
}

/**
 * Append processwire version index to selected items.
 *
 * Called when action is to download processwire versions of selected items.
 *
 * @param Objected selectedItems Selected items to action.
 *
 */
function setVersionsIndexes(form, selectedItems) {
    var versionIndexes = '';
    selectedItems.each(function () {
        var p = ($(this)).parent();
        var version = p.attr('data-version-index');
        versionIndexes += '<input name="ms_processwire_version_index[]" type="hidden" value="'+version+'">';
    });
    form.append(versionIndexes);
}

/**
 * Helper function to store selector string values.
 *
 * Used in conjunction with setInputs().
 * 
 * @return object siteFields Object with selector values.
 *
 */
function getsiteFields() {
    
    var siteFields = {};
    
    siteFields = {
        // sections: div class
        allSections: '.ms_section',

        // individual sections: div IDs
        siteSection: '#ms_site_section',
        databaseSection: '#ms_database_section',
        superUserSection: '#ms_superuser_section',
        filePermissionSection: '#ms_file_permissions_section',
        
        // headers: div class
        allHeaders: '.ms_setting_header',

        // sub-section wrappers: div IDs
        title: '#ms_site_title_wrapper',// site title
        desc: '#ms_site_description_wrapper',// description
        siteType: '#ms_create_site_type_wrapper',// single vs multi-site radio select
        createMethod: '#ms_create_method_wrapper',// how site being created
        siteDir: '#ms_site_directory_wrapper',// multi-site install directory
        installDir: '#ms_site_install_directory_wrapper',// single-site install directory
        pwVersion: '#ms_create_pw_version_select_wrapper',// saved values (JSON) to create site
        httpHostNames: '#ms_http_host_names_wrapper',// cannot copy paste since need to separate by line
        typePaste: '#ms_create_copy_paste_wrapper',// type/paste key=value, pairs to create site
        savedConfigs: '#ms_create_json_configs_wrapper',// saved values (JSON) to create site
        profiles: '#ms_installation_profile_wrapper',
        
        // radios: input names
        siteTypeRadio: 'input:radio[name="ms_create_site_type"]', 
        createMethodRadio: 'input:radio[name="ms_create_method"]',

        // configurable inputs wrappers:  div classe (@note: these are the values that can be saved as JSON)
        configurableInputs: '.ms_configurable',
    };

    return siteFields;

}

/**
 * Dynamically hide/show site creation inputs depending on creation method.
 * 
 * @param integer siteTypeValue Whether creating a single versus multi-site
 * @param integer createMethodValue Denotes site creation method (form, copy-paste or saved configs)
 *
 */
function setInputs(siteTypeValue, createMethodValue) {

    var siteFields = getsiteFields();

    var allSections = siteFields.allSections + ':not(' + siteFields.siteSection + ')';
    var filePermissionSection = siteFields.filePermissionSection;
    
    var allHeadersExceptions = '';
    allHeadersExceptions += siteFields.title + ',';
    allHeadersExceptions += siteFields.desc + ',';
    allHeadersExceptions += siteFields.siteType + ',';
    allHeadersExceptions += siteFields.profiles + ',';
    allHeadersExceptions += siteFields.httpHostNames + ',';
    allHeadersExceptions += siteFields.createMethod;
    
    var allHeaders = siteFields.allHeaders + ':not(' + allHeadersExceptions + ')';
    
    var siteDir = siteFields.siteDir;
    var installDir = siteFields.installDir;
    var pwVersion = siteFields.pwVersion;
    var typePaste = siteFields.typePaste;
    var savedConfigs = siteFields.savedConfigs;
    var profiles = siteFields.profiles;

    var configurableInputs = siteFields.configurableInputs;    
    
    var siteType;
    // creating from form
    if (createMethodValue == 1) {
        $(allSections + ',' + allHeaders).removeClass('ms_hide');
        if (siteTypeValue == 1) siteType = siteDir;
        else if (siteTypeValue == 2) siteType = installDir + ',' + pwVersion;
        $(siteType + ',' + typePaste + ',' + savedConfigs).addClass('ms_hide');
    }
    // creating from type or paste
    else if (createMethodValue == 2) {
        $(allSections + ',' + allHeaders).not(profiles).addClass('ms_hide');    
        if (siteTypeValue == 1) siteType = installDir + ',' + pwVersion + ',';
        else if (siteTypeValue == 2) siteType = '';
        $(siteType + typePaste + ',' + profiles).removeClass('ms_hide');
    }
    // creating from saved install configurations
    else if (createMethodValue == 3) {
        $(allSections + ',' + allHeaders).removeClass('ms_hide');    
        if (siteTypeValue == 1) siteType = siteDir;
        else if (siteTypeValue == 2) siteType = installDir + ',' + pwVersion;
        $(siteType + ',' + typePaste + ',' + configurableInputs + ',' + filePermissionSection).addClass('ms_hide');
    }


}


/*************************************************************/
// READY

$(document).ready(function () {

    // highlight required inputs
    var i = $('div.ms_form_wrapper input,div.ms_form_wrapper email,div.ms_form_wrapper password,div.ms_form_wrapper select, textarea#ms_create_copy_paste, div#ms_profile_upload input').not('input#ms_upload_profile_file');
    requiredBackgroundColor(i);
    
    // @todo:?! Not using this for now. From original PW install for showing profile screenshot
    /* $('#select-profile').change(function() {
        $('.profile-preview').hide();	
        $('#' + $(this).val()).fadeIn('fast');
    }).change(); */

    // init autocomplete
    var i = $( "input#ms_timezone");
    timeZonesAutocomplete(i);

    // toggle all checkboxes in the list of item
    $(document).on('change', 'input#ms_toggle_all', function() {
        if ($(this).prop('checked')) $('div.InputfieldContent input:checkbox[name="ms_items_action_selected[]"]').prop('checked', true);
        else $('div.InputfieldContent input:checkbox[name="ms_items_action_selected[]"]').prop('checked', false);
    });

    // set inputs dynamically as required RE site creation and method types
    var siteFields = getsiteFields();
    var siteTypeRadio = siteFields.siteTypeRadio;
    var createMethodRadio = siteFields.createMethodRadio;
    var siteTypeCreateRadios = siteTypeRadio + ',' + createMethodRadio;
    
    $('div#content').on('change', siteTypeCreateRadios, function () {
        var siteTypeValue = $(siteTypeRadio).filter(":checked").val();// jQuery object
        var createMethodValue = $(createMethodRadio).filter(":checked").val();// jQuery object
        setInputs(siteTypeValue, createMethodValue);
    });   

    // pagination limit change
    // @note: workaround for PW issue #784 (GitHub)
    $('select#limit').change(function () { $(this).closest('form').submit(); });
    
    // force parent page refresh on modal close
    $('a.ms_edit_profile, a.ms_edit_config').on('pw-modal-closed', function(evt, ui) {
		window.location.reload(true);
    });
    
    // variables for forms validation
    var confirm = $('input#ms_confirm');
    var popupData = $('span#ms_popup_data');
    var noItemsPopupData = $('span#ms_no_items_popup_data');
    var noActionPopupData = $('span#ms_no_action_popup_data');

    // CREATE SITE FORM VALIDATION
    $('form#ms_create_form').submit(function (e) {
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed        
            var valid = {};            
            // for popup
            var popupErrorsList = $('ol#ms_validation_errors');
            popupErrorsList.children().remove();    
            //+++++++++++++++++++++++++++++++++++++++++++++
            valid = validateForm(form);// returns object

            if (1 == valid.errors) {
                e.preventDefault(); 
                notices = valid.notices;
                $.each(notices, function (index, popupMessage) {
                    popupErrorsList.append('<li>' + popupMessage + '</li>');
                });
    
                initPopup(popupData);
            }
    
            // submit form
            else {                
                confirm.val(1);
                $('button#ms_create_btn').click();
                var i = $('span#ms_spinner i');
                spinner(i, mode = 'in');
            }
        
        }

    })

    // INSTALLED SITES ACTION CONFIRM
    $('div#ms_installed_sites_action_confirm').on('click', 'button#ms_installed_sites_action_confirm_btn', function() {
        $('input#ms_confirm').val(1)    
        $('button#ms_installed_btn').click();
    });
    
    $('form#ms_installed_sites_form').submit(function (e) {
        var form = $(this);
        var s = $('select#ms_items_action_select');
        var action = s.val();
        // check if items selected
        var selectedItems = getSelectedItems();

        if (action == 'lock' || action == 'unlock') return;
        if (0 == confirm.val() && selectedItems.length) {
            e.preventDefault();
            // remove previous lists 
            $('p.ms_confirm_list').remove();
            $('div#ms_installed_sites_action_confirm ol').children().remove();            
            // files delete only
            if (action == 'delete_directory') buildDeleteList(selectedItems, 1);
            // database delete only
            else if (action == 'delete_database') buildDeleteList(selectedItems, 2);            
            // delete both files and databases
            else {
                buildDeleteList(selectedItems, 1);
                buildDeleteList(selectedItems, 2);
            }
            // call magnific
            initPopup(popupData);        
        }// END if action not confirmed & items selected
    })

    // UPLOAD PROFILE FORM VALIDATION
    $('form#ms_profile_upload_form.ms_new_profile').submit(function (e) {
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed
            var valid = {};            
            valid = {
                errors: 0,
                notices: []
            };
            // for popup
            var popupErrors = $('div#ms_validation');
            popupErrors.children('p.ms_error').remove();
            //+++++++++++++++++++++++++++++++++++++++++++++
            valid = validateRequieredFields(form, valid);

            if (1 == valid.errors) {
                notices = valid.notices;
                $.each(notices, function (index, popupMessage) {
                    popupErrors.append('<p class="ms_error">' + popupMessage + '</p>');                    
                });
                e.preventDefault();    
                initPopup(popupData);
            }
    
            // submit form
            else {
                confirm.val(1);
                $('button#ms_upload_btn').click();
            }
        
        }

    })

    // ADD CONFIG FORM VALIDATION
    $('form#ms_config_add_form').submit(function (e) {        
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed        
            var valid = {};            
            // for popup
            var popupErrorsList = $('ol#ms_validation_errors');
            popupErrorsList.children().remove();    
            //+++++++++++++++++++++++++++++++++++++++++++++
            valid = validateForm(form);// returns object

            if (1 == valid.errors) {
                e.preventDefault(); 
                notices = valid.notices;
                $.each(notices, function (index, popupMessage) {
                    popupErrorsList.append('<li>' + popupMessage + '</li>');
                });
    
                initPopup(popupData);
            }
    
            // submit form
            else {                
                confirm.val(1);
                $('button#ms_create_btn').click();
                var i = $('span#ms_spinner i');
                spinner(i, mode = 'in');
            }
        
        }

    })

    // PROCESSWIRE VERSIONS FORM
    $('div#ms_top_action_selects select#ms_items_action_select').change(function () {
        var v = $(this).val();
        if ('download' == v) $('p#ms_download_warning').fadeIn('fast').removeClass('ms_hide');
        else $('p#ms_download_warning').fadeOut('fast').addClass('ms_hide');
    });

    $('form#ms_processwire_versions_form').submit(function (e) {
        var form = $(this);
        var s = $('select#ms_items_action_select');
        var action = s.val();
        // check if items selected
        var selectedItems = getSelectedItems();
        // if downloading, append version indexes to form
        if (action == 'download') {
            var i = $('span#ms_spinner i');
            spinner(i, mode='in');     
            setVersionsIndexes(form, selectedItems)
        }
        
    })

    // no items selected on click
    $(document).on('click', 'button.ms_bulk_action_btn', function (e) {
        var selectedItems = getSelectedItems();
        var actionsSelect = $('select#ms_items_action_select');
        if (!selectedItems.length) {
            e.preventDefault();
            initPopup(noItemsPopupData);
        }

        else if ( 'select' == actionsSelect.val() ) {
            e.preventDefault();
            initPopup(noActionPopupData);
        }
    });
    

});//END doc ready


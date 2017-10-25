/**
 *
 * Javascript file for the ProcessWire Module Sites manager (Process)
 *
 * @author Francis Otieno (Kongondo)
 *
 * Copyright (C) 2017 by Francis Otieno
 *
 */

/**
 * Get all time zones and their values to populate autocomplete.
 *
 * We get the values from a JS Object that was set in SitesManagerRender::renderTimeZonesScript.
 * This is used by timeZonesAutocomplete() in single edit variations congiguration.
 *
 * @return Array timeZones Contains indexed array of time zones and their respective values.
 *
*/
function getTimezones() {
	var timeZones = {};
	// check if time zones values object is loaded
	if (typeof sitesManagerTimeZonesConfig == 'object') timeZones = sitesManagerTimeZonesConfig;
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
            $('input#sm_timezone_id').val(selectedTimeZoneID)
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
                $('div#sm_installed_sites_action_confirm').on('click', 'button#sm_installed_sites_action_cancel_btn', function (e) {
                    clearDeleteList();
                    magnific.close();
                })

			}// END open: callback
		},
		closeMarkup: closeMarkup,		
		mainClass: 'sm_popups',
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
    }).addClass('sm_required');
    // detect input changes on required fields and remove class for background-color if input not empty
    i.on('input', function() {
        if($(this).val() == '') $(this).addClass('sm_required')
        else $(this).removeClass('sm_required')
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
    var out = title ? '<div id="sm_popups_close" class="NoticeError ui-helper-clearfix"><span id="sm_popup_title">' + title + '</span><span class="mfp-close">&#215;</span></div>' : '';
    return out;
}

// ##  VALIDATORS ## 

/**
 * 
 * 
 * @param Object form The form to validate.
 * @returns Object valid Object with feedback info if form did not validate.
 *
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
 * @param Object form The form to validate.
 * @param Object valid For user feedback if form did not validate.
 * @returns Object valid Object with feedback info if form did not validate.
 *
 */
function validateRequieredFields(form, valid) {
    var inputs = form.find('input, select, textarea#sm_create_copy_paste').not('input#sm_timezone_id,input#sm_confirm');
    inputs.each(function () {
        var i = ($(this));
        var val = i.val();
        var hiddenParent = i.closest('div.sm_hide');
        if (hiddenParent.length) return;
        if (0 == val) {
            valid.errors = 1;
            return false;
        }
    });
    var notice = $('p#sm_required_fields').text();
    valid.notices.push(notice);
    return valid;
}

/**
 * 
 * 
 * @param Object form The form to validate.
 * @param Object valid For user feedback if form did not validate.
 * @returns Object valid Object with feedback info if form did not validate.
 *
 */
function validateAdminName(form, valid) {

    var admin = form.find('input#sm_admin_url');
    var hiddenParent = admin.closest('div.sm_hide');
    if(hiddenParent.length) return valid;
    //var adminName = form.find('input#sm_admin_url').val();
    var adminName = admin.val();

    // admin name disallowed ('wire' and 'site')
    if (adminName == 'wire' || adminName == 'site') {
        valid.errors = 1;
        var notice = $('p#sm_admin_name_disallowed').text();
        valid.notices.push(notice);
    }

    // admin name too short [shoudl be at least 2 char]
    if (admin.length && adminName.length < 2) {
        valid.errors = 1;
        var notice = $('p#sm_admin_name_short').text();
        valid.notices.push(notice);
    }

    // admin name disallowed characters [a-z 0-9]
    if (!validateCharacters(adminName)) {
        valid.errors = 1;
        var notice = $('p#sm_admin_name_characters_disallowed').text();
        valid.notices.push(notice);
    }

    return valid;
}

/**
 * 
 * 
 * @param Object form The form to validate.
 * @param Object valid For user feedback if form did not validate.
 * @returns Object valid Object with feedback info if form did not validate.
 *
 */
function validateSuperUser(form, valid) {

    var name = form.find('input#sm_superuser_name');
    var password = form.find('input#sm_superuser_pass');
    var passwordConfirm = form.find('input#sm_superuser_pass_confirm');
    var email = form.find('input#sm_superuser_email');

    var superUserName = name.val();
    var superUserPassword = password.val();
    var superUserPasswordConfirm = passwordConfirm.val();
    var superUserEmail = email.val();

    var hiddenParentName = name.closest('div.sm_hide');    
    if (!hiddenParentName.length) {
        // superuser name disallowed characters [a-z 0-9]
        if (!validateCharacters(superUserName)) {
            valid.errors = 1;
            var notice = $('p#sm_superuser_name_characters_disallowed').text();
            valid.notices.push(notice);
        }
        // superuser name too short
        if (superUserName.length < 2) {
            valid.errors = 1;
            var notice = $('p#sm_superuser_name_short').text();
            valid.notices.push(notice);
        } 
    }
    
    var hiddenParentPassword = password.closest('div.sm_hide');
    if (!hiddenParentPassword.length) { 
        // superuser passwords mismatch
        if (superUserPassword !== superUserPasswordConfirm) {
            valid.errors = 1;
            var notice = $('p#sm_superuser_passwords_mismatch').text();
            valid.notices.push(notice);
        }
        // superuser password too short [should be at least 6 char]
        if (password.length && superUserPassword.length < 6) {
            valid.errors = 1;
            var notice = $('p#sm_superuser_password_short').text();
            valid.notices.push(notice);
        }
    }
    
    var hiddenParentEmail = email.closest('div.sm_hide');
    if (!hiddenParentEmail.length) { 
        // superuser email invalid
        if (!validateEmail(superUserEmail)) {
            valid.errors = 1;
            var notice = $('p#sm_superuser_email_invalid').text();
            valid.notices.push(notice);
        }
    }

    return valid;
    
}
    
/**
 * Check if specified email is validly formatted.
 * 
 * @param String email Email to validate.
 * @returns Boolean emailReg Whether email validated or not.
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
 * @returns Object selectedItems All items selected for bulk actioning.
 *
 */
function getSelectedItems() {
    var selectedItems = $('input[name="sm_itesm_action_selected[]"]:checked');    
	return selectedItems;
}

/**
 * Build list of directories and/or databases that will be deleted.
 * 
 * @param Object selectedItems Collection of checked items to action.
 * @param Integer mode Whether to deleting files or databases.
 *
 */
function buildDeleteList(selectedItems, mode) {
    var deleteFilesList = getDeleteList(1);
    var deleteDatabasesList = getDeleteList(2);
    var dataSrc
    var id;
    // delete files mode
    if (1 == mode) {
        id = 'sm_delete_sites_files_confirm';
        buildDeleteListHeader(id,deleteFilesList);
        dataSrc = 'data-directory';
        appendDeleteList(selectedItems, dataSrc, deleteFilesList);
    }
    // delete databases mode
    else if (2 == mode) {
        id = 'sm_delete_sites_databases_confirm';
        buildDeleteListHeader(id,deleteDatabasesList);
        dataSrc = 'data-database';
        appendDeleteList(selectedItems, dataSrc, deleteDatabasesList);
    }

}

/**
 * Append names of items that will be deleted to feedback modal.
 * 
 * @param object items Collection of items from which to find data to append as notices.
 * @param string dataSrc The data attribute to get text with details of items that will be actioned.
 * @param object list The element to append notices to.
 *
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
    $('<p class="sm_confirm_list">' + popupMessage + '</p>').insertBefore(list); 
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
    if (1 == mode) list = $('ol#sm_installed_sites_delete_files_confirm_list');
    else list = $('ol#sm_installed_sites_delete_databases_confirm_list');
    return list;
}

/**
 * Uncheck items that were to be actioned before cancellation of action.
 * 
 */
function clearDeleteList() {
    var selectedItems = getSelectedItems();
    $('input#sm_toggle_all').prop("checked", false);
    $.each(selectedItems, function () {this.checked = false;});
    $('select#sm_itesm_action_select').val('select');
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
	if(mode == 'in') i.removeClass('sm_hide');
	else {
        setTimeout(function(){
            $(i).addClass('sm_hide');
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
        versionIndexes += '<input name="sm_processwire_version_index[]" type="hidden" value="'+version+'">';
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
        allSections: '.sm_section',

        // individual sections: div IDs
        siteSection: '#sm_site_section',
        databaseSection: '#sm_database_section',
        superUserSection: '#sm_superuser_section',
        filePermissionSection: '#sm_file_permissions_section',
        
        // headers: div class
        allHeaders: '.sm_setting_header',

        // sub-section wrappers: div IDs
        title: '#sm_site_title_wrapper',// site title
        desc: '#sm_site_description_wrapper',// description
        siteType: '#sm_create_site_type_wrapper',// single vs multi-site radio select
        createMethod: '#sm_create_method_wrapper',// how site being created
        siteDir: '#sm_site_directory_wrapper',// multi-site install directory
        installDir: '#sm_site_install_directory_wrapper',// single-site install directory
        pwVersion: '#sm_create_pw_version_select_wrapper',// saved values (JSON) to create site
        httpHostNames: '#sm_http_host_names_wrapper',// cannot copy paste since need to separate by line
        typePaste: '#sm_create_copy_paste_wrapper',// type/paste key=value, pairs to create site
        savedConfigs: '#sm_create_json_configs_wrapper',// saved values (JSON) to create site
        profiles: '#sm_installation_profile_wrapper',
        
        // radios: input names
        siteTypeRadio: 'input:radio[name="sm_create_site_type"]', 
        createMethodRadio: 'input:radio[name="sm_create_method"]',

        // configurable inputs wrappers:  div classe (@note: these are the values that can be saved as JSON)
        configurableInputs: '.sm_configurable',
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
        $(allSections + ',' + allHeaders).removeClass('sm_hide');
        if (siteTypeValue == 1) siteType = siteDir;
        else if (siteTypeValue == 2) siteType = installDir + ',' + pwVersion;
        $(siteType + ',' + typePaste + ',' + savedConfigs).addClass('sm_hide');
    }
    // creating from type or paste
    else if (createMethodValue == 2) {
        $(allSections + ',' + allHeaders).not(profiles).addClass('sm_hide');    
        if (siteTypeValue == 1) siteType = installDir + ',' + pwVersion + ',';
        else if (siteTypeValue == 2) siteType = '';
        $(siteType + typePaste + ',' + profiles).removeClass('sm_hide');
    }
    // creating from saved install configurations
    else if (createMethodValue == 3) {
        $(allSections + ',' + allHeaders).removeClass('sm_hide');    
        if (siteTypeValue == 1) siteType = siteDir;
        else if (siteTypeValue == 2) siteType = installDir + ',' + pwVersion;
        $(siteType + ',' + typePaste + ',' + configurableInputs + ',' + filePermissionSection).addClass('sm_hide');
    }


}

/*************************************************************/
// READY

$(document).ready(function () {    

    // highlight required inputs
    var i = $('div.sm_form_wrapper input,div.sm_form_wrapper email,div.sm_form_wrapper password,div.sm_form_wrapper select, textarea#sm_create_copy_paste, div#sm_profile_upload input').not('input#sm_upload_profile_file');
    requiredBackgroundColor(i);
    
    // @todo:?! Not using this for now. From original PW install for showing profile screenshot
    /* $('#select-profile').change(function() {
        $('.profile-preview').hide();	
        $('#' + $(this).val()).fadeIn('fast');
    }).change(); */

    // init autocomplete
    var i = $( "input#sm_timezone");
    timeZonesAutocomplete(i);

    // toggle all checkboxes in the list of item
    $(document).on('change', 'input#sm_toggle_all', function() {
        if ($(this).prop('checked')) $('div.InputfieldContent input:checkbox[name="sm_itesm_action_selected[]"]').prop('checked', true);
        else $('div.InputfieldContent input:checkbox[name="sm_itesm_action_selected[]"]').prop('checked', false);
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
    $('a.sm_edit_profile, a.sm_edit_config').on('pw-modal-closed', function(evt, ui) {
		window.location.reload(true);
    });
    
    // variables for forms validation
    var confirm = $('input#sm_confirm');
    var popupData = $('span#sm_popup_data');
    var noItemsPopupData = $('span#sm_no_itesm_popup_data');
    var noActionPopupData = $('span#sm_no_action_popup_data');

    // CREATE SITE FORM VALIDATION
    $('form#sm_create_form').submit(function (e) {
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed        
            var valid = {};            
            // for popup
            var popupErrorsList = $('ol#sm_validation_errors');
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
                $('button#sm_create_btn').click();
                var i = $('span#sm_spinner i');
                spinner(i, mode = 'in');
            }
        
        }

    })

    // INSTALLED SITES ACTION CONFIRM
    $('div#sm_installed_sites_action_confirm').on('click', 'button#sm_installed_sites_action_confirm_btn', function() {
        $('input#sm_confirm').val(1)    
        $('button#sm_installed_btn').click();
    });
    
    $('form#sm_installed_sites_form').submit(function (e) {
        var form = $(this);
        var s = $('select#sm_itesm_action_select');
        var action = s.val();
        // check if items selected
        var selectedItems = getSelectedItems();

        if (action == 'lock' || action == 'unlock') return;
        if (0 == confirm.val() && selectedItems.length) {
            e.preventDefault();
            // remove previous lists 
            $('p.sm_confirm_list').remove();
            $('div#sm_installed_sites_action_confirm ol').children().remove();            
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
    $('form#sm_profile_upload_form.sm_new_profile').submit(function (e) {
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed
            var valid = {};            
            valid = {
                errors: 0,
                notices: []
            };
            // for popup
            var popupErrors = $('div#sm_validation');
            popupErrors.children('p.sm_error').remove();
            //+++++++++++++++++++++++++++++++++++++++++++++
            valid = validateRequieredFields(form, valid);

            if (1 == valid.errors) {
                notices = valid.notices;
                $.each(notices, function (index, popupMessage) {
                    popupErrors.append('<p class="sm_error">' + popupMessage + '</p>');                    
                });
                e.preventDefault();    
                initPopup(popupData);
            }
    
            // submit form
            else {
                confirm.val(1);
                $('button#sm_upload_btn').click();
            }
        
        }

    })

    // ADD CONFIG FORM VALIDATION
    $('form#sm_config_add_form').submit(function (e) {        
        var form = $(this);
        if (0 == confirm.val()) {// @todo:? this not really needed        
            var valid = {};            
            // for popup
            var popupErrorsList = $('ol#sm_validation_errors');
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
                $('button#sm_create_btn').click();
                var i = $('span#sm_spinner i');
                spinner(i, mode = 'in');
            }
        
        }

    })

    // PROCESSWIRE VERSIONS FORM
    $('div#sm_top_action_selects select#sm_itesm_action_select').change(function () {
        var v = $(this).val();
        if ('download' == v) $('p#sm_download_warning').fadeIn('fast').removeClass('sm_hide');
        else $('p#sm_download_warning').fadeOut('fast').addClass('sm_hide');
    });

    $('form#sm_processwire_versions_form').submit(function (e) {
        var form = $(this);
        var s = $('select#sm_itesm_action_select');
        var action = s.val();
        // check if items selected
        var selectedItems = getSelectedItems();
        // if downloading, append version indexes to form
        if (action == 'download') {
            var i = $('span#sm_spinner i');
            spinner(i, mode='in');     
            setVersionsIndexes(form, selectedItems)
        }
        
    })

    // no items selected on click
    $(document).on('click', 'button.sm_bulk_action_btn', function (e) {
        var selectedItems = getSelectedItems();
        var actionsSelect = $('select#sm_itesm_action_select');
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


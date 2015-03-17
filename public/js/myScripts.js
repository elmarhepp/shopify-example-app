/* Scripts for Shopify Example App */

// global variables
var shopURL = '';

// JQuery ready
$(document).ready(function () {

    checkApplicationCharge();
    // set shopURL
    shopURL = $('#shopURL').val();

}); // ####### END OF document.ready


function checkApplicationCharge() {
    var confirmationURL = $('#confirmationURL').val();
    var useConfirmationURL = $('#useConfirmationURL').val();
    console.log('checkApplicationCharge useConfirmationURL=' + useConfirmationURL + ', confirmationURL=' + confirmationURL);

    if (useConfirmationURL == 'true') {
        console.log('checkApplicationCharge redirect to ' + confirmationURL);
        ShopifyApp.redirect(confirmationURL);
    }
}


/**
 * redirect to products page
 */
function redirectToExampleApp() {
    window.location.replace("index?shopURL=" + shopURL);
}


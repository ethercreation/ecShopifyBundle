pimcore.registerNS("pimcore.plugin.ecShopifyBundle");

pimcore.plugin.ecShopifyBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("ecShopifyBundle ready!");
    }
});

var ecShopifyBundlePlugin = new pimcore.plugin.ecShopifyBundle();

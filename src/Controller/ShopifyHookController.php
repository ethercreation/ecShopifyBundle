<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to a commercial license from SARL Ether Création
 * Use, copy, modification or distribution of this source file without written
 * license agreement from the SARL Ether Création is strictly forbidden.
 * In order to obtain a license, please contact us: contact@ethercreation.com
 * ...........................................................................
 * INFORMATION SUR LA LICENCE D'UTILISATION
 *
 * L'utilisation de ce fichier source est soumise a une licence commerciale
 * concedee par la societe Ether Création
 * Toute utilisation, reproduction, modification ou distribution du present
 * fichier source sans contrat de licence ecrit de la part de la SARL Ether Création est
 * expressement interdite.
 * Pour obtenir une licence, veuillez contacter la SARL Ether Création a l'adresse: contact@ethercreation.com
 * ...........................................................................
 *
 * @author    Ether Création SARL <contact@ethercreation.com>
 * @copyright 2008-2018 Ether Création SARL
 * @license   Commercial license
 * International Registered Trademark & Property of PrestaShop Ether Création SARL
 *
 */

namespace bundles\ecShopifyBundle\Controller;

use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecShopifyBundle\Services\ShopifyApiClient;
use CustomerManagementFrameworkBundle\RESTApi\Response;
use http\Env\Request;
use Shopify\Exception\RestResourceException;
use Shopify\Rest\Admin2022_04\Order;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ShopifyHookController
 */
#[Route('/hook-shopify', name: 'ec_shopify_hook')]
class ShopifyHookController extends DefaultController
{
    /**
     * @throws RestResourceException
     * @throws \Exception
     */
    #[Route('/create-order', name: 'ec_shopify_hook_create_order')]
    public function createOrderAction(): Response
    {
        $test = '{"order":{"id":5465556418862,"admin_graphql_api_id":"gid:\/\/shopify\/Order\/5465556418862","app_id":1354745,"browser_ip":"89.83.126.25","buyer_accepts_marketing":false,"cancel_reason":null,"cancelled_at":null,"cart_token":null,"checkout_id":37005279789358,"checkout_token":"483af053c4b649992e070c72e03b874c","client_details":{"accept_language":null,"browser_height":null,"browser_ip":"89.83.126.25","browser_width":null,"session_hash":null,"user_agent":"Mozilla\/5.0 (X11; Linux x86_64) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/116.0.0.0 Safari\/537.36"},"closed_at":null,"confirmed":true,"contact_email":"john.wayne@gmail.com","created_at":"2023-09-18T08:32:47-04:00","currency":"EUR","current_subtotal_price":"15.00","current_subtotal_price_set":{"shop_money":{"amount":"15.00","currency_code":"EUR"},"presentment_money":{"amount":"15.00","currency_code":"EUR"}},"current_total_discounts":"0.00","current_total_discounts_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"EUR"}},"current_total_duties_set":null,"current_total_price":"18.00","current_total_price_set":{"shop_money":{"amount":"18.00","currency_code":"EUR"},"presentment_money":{"amount":"18.00","currency_code":"EUR"}},"current_total_tax":"3.00","current_total_tax_set":{"shop_money":{"amount":"3.00","currency_code":"EUR"},"presentment_money":{"amount":"3.00","currency_code":"EUR"}},"customer_locale":"en","device_id":null,"discount_codes":[],"email":"john.wayne@gmail.com","estimated_taxes":false,"financial_status":"paid","fulfillment_status":null,"gateway":"manual","landing_site":null,"landing_site_ref":null,"location_id":null,"merchant_of_record_app_id":null,"name":"#1001","note":null,"note_attributes":[],"number":1,"order_number":1001,"order_status_url":"https:\/\/ether-creation-alpha.myshopify.com\/74635968814\/orders\/bdc62202039cf6143a0892207a238034\/authenticate?key=93f038388d60de31f26c28447ef747a1","original_total_duties_set":null,"payment_gateway_names":["manual"],"phone":null,"presentment_currency":"EUR","processed_at":"2023-09-18T08:32:47-04:00","processing_method":"manual","reference":"dba5a24bd58231d7bf7b4ce9089624af","referring_site":null,"source_identifier":"dba5a24bd58231d7bf7b4ce9089624af","source_name":"shopify_draft_order","source_url":null,"subtotal_price":"15.00","subtotal_price_set":{"shop_money":{"amount":"15.00","currency_code":"EUR"},"presentment_money":{"amount":"15.00","currency_code":"EUR"}},"tags":"","tax_lines":[{"price":"3.00","rate":0.2,"title":"FR TVA","price_set":{"shop_money":{"amount":"3.00","currency_code":"EUR"},"presentment_money":{"amount":"3.00","currency_code":"EUR"}},"channel_liable":false}],"taxes_included":false,"test":false,"token":"bdc62202039cf6143a0892207a238034","total_discounts":"0.00","total_discounts_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"EUR"}},"total_line_items_price":"15.00","total_line_items_price_set":{"shop_money":{"amount":"15.00","currency_code":"EUR"},"presentment_money":{"amount":"15.00","currency_code":"EUR"}},"total_outstanding":"0.00","total_price":"18.00","total_price_set":{"shop_money":{"amount":"18.00","currency_code":"EUR"},"presentment_money":{"amount":"18.00","currency_code":"EUR"}},"total_shipping_price_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"EUR"}},"total_tax":"3.00","total_tax_set":{"shop_money":{"amount":"3.00","currency_code":"EUR"},"presentment_money":{"amount":"3.00","currency_code":"EUR"}},"total_tip_received":"0.00","total_weight":1250,"updated_at":"2023-09-18T08:32:48-04:00","user_id":95222890798,"billing_address":{"first_name":"John","address1":"Le Manoir des Doyens","phone":"+33245631255","city":"Bayeux","zip":"14400","province":null,"country":"France","last_name":"Wayne","address2":null,"company":"Wayne industry","latitude":49.2661482,"longitude":-0.716318,"name":"John Wayne","country_code":"FR","province_code":null},"customer":{"id":7408377626926,"email":"john.wayne@gmail.com","accepts_marketing":false,"created_at":"2023-09-18T08:32:30-04:00","updated_at":"2023-09-18T08:32:48-04:00","first_name":"John","last_name":"Wayne","state":"disabled","note":null,"verified_email":true,"multipass_identifier":null,"tax_exempt":false,"phone":null,"email_marketing_consent":{"state":"not_subscribed","opt_in_level":"single_opt_in","consent_updated_at":null},"sms_marketing_consent":null,"tags":"","currency":"EUR","accepts_marketing_updated_at":"2023-09-18T08:32:30-04:00","marketing_opt_in_level":null,"tax_exemptions":[],"admin_graphql_api_id":"gid:\/\/shopify\/Customer\/7408377626926","default_address":{"id":9601835106606,"customer_id":7408377626926,"first_name":"John","last_name":"Wayne","company":"Wayne industry","address1":"Le Manoir des Doyens","address2":"","city":"Bayeux","province":null,"country":"France","zip":"14400","phone":"+33245631255","name":"John Wayne","province_code":null,"country_code":"FR","country_name":"France","default":true}},"discount_applications":[],"fulfillments":[],"line_items":[{"id":14111769657646,"admin_graphql_api_id":"gid:\/\/shopify\/LineItem\/14111769657646","fulfillable_quantity":1,"fulfillment_service":"manual","fulfillment_status":null,"gift_card":false,"grams":1250,"name":"Example Pants - Blue","price":"15.00","price_set":{"shop_money":{"amount":"15.00","currency_code":"EUR"},"presentment_money":{"amount":"15.00","currency_code":"EUR"}},"product_exists":true,"product_id":8697153585454,"properties":[],"quantity":1,"requires_shipping":true,"sku":"REFOURNISSEUR","taxable":true,"title":"Example Pants","total_discount":"0.00","total_discount_set":{"shop_money":{"amount":"0.00","currency_code":"EUR"},"presentment_money":{"amount":"0.00","currency_code":"EUR"}},"variant_id":46970360987950,"variant_inventory_management":null,"variant_title":"Blue","vendor":"Ether Création - Alpha","tax_lines":[{"channel_liable":false,"price":"3.00","price_set":{"shop_money":{"amount":"3.00","currency_code":"EUR"},"presentment_money":{"amount":"3.00","currency_code":"EUR"}},"rate":0.2,"title":"FR TVA"}],"duties":[],"discount_allocations":[]}],"payment_terms":null,"refunds":[],"shipping_address":{"first_name":"John","address1":"Le Manoir des Doyens","phone":"+33245631255","city":"Bayeux","zip":"14400","province":null,"country":"France","last_name":"Wayne","address2":null,"company":"Wayne industry","latitude":49.2661482,"longitude":-0.716318,"name":"John Wayne","country_code":"FR","province_code":null},"shipping_lines":[]}}';
        /** @var Order $shopify_order */
        $shopify_order = new JSONObject(json_decode($test, true)['order']);
//        $shopify_order =  json_decode($test, true)['order'];
//        $shopify_order = new Order(json_decode($request->getBody(), true)['order']);
        $pimcore_order = new \Pimcore\Model\DataObject\Order();
        $pimcore_order->setId_order($shopify_order->id);
        // TODO: ID Client
        // TODO: ID Adresse Livraison
        // TODO: Etape en cours
        // TODO: Moyen de paiement$
        $pimcore_order->setTotal_discounts($shopify_order->total_discounts);
        $pimcore_order->setTotal_paid($shopify_order->total_price);

        Outils::addLog(json_encode($pimcore_order), [], [], 'ecShopifyBundle');
        $result = $pimcore_order->save();
        return new Response($result);
    }
}
class JSONObject {
    public function __construct($json = false) {
        if ($json) $this->set($json);
    }

    public function set($data): void
    {
        foreach ($data AS $key => $value) {
            if (is_array($value)) {
                $sub = new JSONObject;
                $sub->set($value);
                $value = $sub;
            }
            $this->{$key} = $value;
        }
    }
}

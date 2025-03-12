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
 * @author    Théodore Riant <theodore@ethercreation.com>
 * @copyright 2008-2018 Ether Création SARL
 * @license   Commercial license
 * International Registered Trademark & Property of PrestaShop Ether Création SARL
 *
 */

namespace bundles\ecShopifyBundle\Controller;

use bundles\ecMiddleBundle\Controller\ecMiddlePrestashopController;
use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecShopifyBundle\Services\PimcoreActions;
use bundles\ecShopifyBundle\Services\ShopifyApiClient;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Diffusion;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\WebsiteSetting;
use Pimcore\Tool;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Shopify\Exception\{InvalidArgumentException,
    MissingArgumentException,
    UninitializedContextException,
    WebhookRegistrationException
};
use Shopify\Rest\Admin2023_04\Product;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    const PARENT_ID_CATEGORY = 1772;
    /**
     * @var mixed|string[]
     */
    private mixed $languages;
    private mixed $list_id_diffusion;
    private mixed $folder;

    public function __construct()
    {
        $this->languages = Tool::getValidLanguages();
        $this->list_id_diffusion = DataObject::getByPath('/Diffusion/Prestashop/list_id_ps')->getValeur();
        $this->folder['folderDeclinaison'] = WebsiteSetting::getByName('folderDeclinaison')->getData();
        $this->folder['folderAttribut'] = WebsiteSetting::getByName('folderAttribut')->getData();;
        $this->folder['folderProduct'] = WebsiteSetting::getByName('folderProduct')->getData();
        $this->folder['folderMarque'] = WebsiteSetting::getByName('folderMarque')->getData();
        $this->folder['folderCarac'] = WebsiteSetting::getByName('folderCarac')->getData();
        $this->folder['folderImages'] = WebsiteSetting::getByName('folderImages')->getData();
    }

    /**
     * @throws MissingArgumentException
     * @throws \Exception
     */
    #[Route('/ec_shopify/import')]
    public function indexAction(Request $request): Response
    {

        // json en eof
        $client = new ShopifyApiClient();
        /** @var Product[] $products */
        $products = $client->getProducts();
        $objects_ids = [];
        foreach ($products as $product) {
            $objects_ids[] = PimcoreActions::createProductAction($product);
        }
//        $object_id = $json[0];
//
//        $object_id = Outils::getObjectByCrossId(crossid: '453388992814', class: 'category', diffusion: Diffusion::getByPath('/Diffusion/Shopify'));
//        Outils::removeCrossid(DataObject::getById($object_id), Diffusion::getByPath('/Diffusion/Shopify'));
//        $client = new ShopifyApiClient();
//        $shopify_resp = $client->createProduct(1524);
        return new Response('<pre>' . json_encode($objects_ids) . '</pre>');
    }

    /**
     * Installation des webhooks
     *
     * @throws InvalidArgumentException
     * @throws ClientExceptionInterface
     * @throws UninitializedContextException
     * @throws WebhookRegistrationException
     */
    #[Route('/ec_shopify/install/webhooks')]
    public function installWebhooksAction(LoggerInterface $logger): Response
    {
        $client = new ShopifyApiClient();
        $result = $client->installWebhooks();
        $logger->debug('installWebhooksAction', [$result]);
        return new Response('<pre>' . json_encode($result) . '</pre>');
    }


    /**
     * @throws \Exception
     */
    public function createProductAction($json)
    {
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $id_diffusion = $diffusion->getId();
        $api = new ShopifyApiClient();
        if (!is_object($diffusion)) {
            return false;
        }

        /** @var Product $data_product */
        $data_product = json_decode(json_encode($json), FALSE);
        $prod = json_decode(json_encode([
            'id' => $json['id'],
//            'id_manufacturer' => null,
//            'id_supplier' => null,
            'id_category_default' => self::PARENT_ID_CATEGORY,
//            'id_shop_default' => null,
            'name' => $json['title'],
            'description' => $json['body_html'],
            'description_short' => $json['body_html'],
            'quantity' => $data_product->variants[0]->inventory_quantity,
            'price' => $data_product->variants[0]->price,
            'wholesale_price' => $data_product->variants[0]->inventory_item->cost,
            'ecotax' => $data_product->variants[0]->price,
            'reference' => $data_product->variants[0]->sku,
//            'supplier_reference' => null,
//            'width' => null,
//            'height' => $prod->variants[0]->height,
//            'depth' => null,
            'weight' => $data_product->variants[0]->weight,
            'ean13' => strlen($data_product->variants[0]->barcode) === 13 ? $data_product->variants[0]->barcode : '1111111111111',
//            'isbn' => null,
//            'upc' => null,
            'link_rewrite' => $data_product->handle,
//            'meta_description' => [
//                '1' => null,
//            ],
//            'meta_keywords' => null,
//            'meta_title' => null,
            'active' => $data_product->status == 'active' ? 1 : 0,
//            'id_tax_rules_group' => null,
//            'lang' => null,
//            'tax_default' => 1

        ], false));


        $categ = array_map(function ($collection) {
            return json_decode(json_encode([
                'id' => $collection->id,
                'id_category' => $collection->id,
                'id_category_default' => $collection->id,
                'name' => $collection->title,
                'active' => 1,
                'description' => $collection->body_html,
                'id_parent' => DataObject::getByPath('/Category/CategoryDiffusion/Shopify')->getId(),
                'link_rewrite' => $collection->handle,
//                'meta_title' => null,
                'meta_keywords' => null,
//                'meta_description' => null,
            ], false));
        }, $api->getProductCollections($prod->id));
        $image = null;
        foreach ($json['images'] as $im){
            $parts = parse_url($im['src']);
            $image[$im['id']] = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
        }

        $attribute = array();
        $values = array();
        $info = array();
        $options = [];
        foreach ($data_product->options as $key => $val) {
            $options[$val->position] = $val;
        }
        foreach ($data_product->variants as $variant) {
            foreach (['option1' => $variant->option1, 'option2' => $variant->option2, 'option3' => $variant->option3] as $key => $val) {
                if (!isset($val)) continue;
                $index = (int)substr($key, -1);
                $attribute[strval($variant->id)][] = [
                    'id' => strval($options[$index]->id) . '-' . $index,
                    'name' => ['1' => $options[$index]->name],
                    'position' => $index,
                    'active' => 1
                ];
                $values[strval($variant->id)][] = [
                    'id' => strval($variant->id) . '-' . $index,
                    'name' => ['1' => $val],
                    'position' => $index,
                    'active' => 1
                ];
            }

            $info[strval($variant->id)] = [
                'reference_declinaison' => $variant->sku,
                'reference' => $variant->sku,
                'quantity' => $variant->inventory_quantity,
                'ean13' => strlen($variant->barcode) === 13 ? $variant->barcode : '1111111111111',
                'weight' => $variant->weight,
                'wholesale_price' => $variant->inventory_item->cost,
                'price' => $variant->price,
                "id_product" => $data_product->id,
                "id" => strval($variant->id),
                "supplier_reference" => $variant->sku,
                "location" => "",
                "isbn" => "1",
                "upc" => "",
                "mpn" => "",
                "unit_price_impact" => "0.000000",
                "ecotax" => "0.000000",
                "minimal_quantity" => "1",
                "low_stock_threshold" => null,
                "low_stock_alert" => "0",
                "default_on" => "1",
                "available_date" => "0000-00-00",
                "id_shop_list" => [],
                "force_id" => false,
//                'tax_default' => 1
                'active' => 1
            ];
        }

        $decli = json_decode(json_encode([
            'attribute' => $attribute,
            'value' => $values,
            'info' => $info
        ]));
        $carac = json_decode(json_encode([
            'active' => 1,
            'feature' => array_map(function ($option) {
                return [
                    'id' => $option['id'],
                    'name' => ['1' => $option['name']],
                    'active' => 1
                ];
            }, $json['options']),
            'value' => array_map(function ($option) {
                return [
                    'active' => 1,
                    'id_feature' => $option['id'],
                    'value' => ['1' => $option['values'][0]]
                ];
            }, $json['options'])
        ]), false);

        $marque = json_decode(
            json: json_encode([
                'id' => $json['vendor'],
                'name' => $json['vendor'],
                'active' => 1,
            ]),
            associative: false
        );


        // $json = json_decode('{"prod":{"tax_name":null,"tax_rate":null,"id_manufacturer":"10","id_supplier":"0","id_category_default":"184","id_shop_default":"1","manufacturer_name":null,"supplier_name":null,"name":{"1":"R\u00e9frig\u00e9rateur SMEG FAB32RCR3 Cr\u00e8me"},"description":{"1":"<h1>DESCRIPTION DU REFRIGERATEUR COMBINE SMEG FAB32RCR3<\/h1><div><br \/><\/div><div><br \/><\/div><div><h2 style=\"margin:0cm 0cm .0001pt;font-size:12pt;font-family:Calibri, sans-serif;\"><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);background:#FFFFFF;\">REFRIGERATEUR :<\/span><\/h2><p class=\"MsoNormal\" style=\"margin:0cm 0cm .0001pt;font-size:12pt;font-family:Calibri, sans-serif;\"><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);background:#FFFFFF;\">Froid ventil\u00e9<\/span><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);\"><br \/><span style=\"background:#FFFFFF;\">Air Plus, syst\u00e8me d\u2019a\u00e9ration multiple<\/span><br \/><span style=\"background:#FFFFFF;\">Volume net : 234 litres<\/span><br \/><span style=\"background:#FFFFFF;\">Tiroir Extra Fresh 0\u00b0C<\/span><br \/><span style=\"background:#FFFFFF;\">Contr\u00f4le \u00e9lectronique de la temp\u00e9rature<\/span><br \/><span style=\"background:#FFFFFF;\">2 clayettes en verre<\/span><br \/><span style=\"background:#FFFFFF;\">1 tiroir l\u00e9gumes avec dessus en verre sur rails<\/span><br \/><span style=\"background:#FFFFFF;\">Bandeaux lumineux \u00e0 LED des deux c\u00f4t\u00e9s<\/span><br \/><br \/><span style=\"background:#FFFFFF;\">Contre porte avec :<\/span><br \/><span style=\"background:#FFFFFF;\">1 balconnet porte-bouteilles<\/span><br \/><span style=\"background:#FFFFFF;\">2 balconnets<\/span><br \/><span style=\"background:#FFFFFF;\">1 balconnet avec couvercle transparent<\/span><br \/><span style=\"background:#FFFFFF;\">1 casier \u00e0 \u0153ufs<\/span><br \/><br \/><\/span><\/p><h2><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);\"><span style=\"background:#FFFFFF;\">CONGELATEUR :<\/span><\/span><\/h2><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);\"><span style=\"background:#FFFFFF;\">No Frost<\/span><br \/><span style=\"background:#FFFFFF;\">Volume net : 97 litres<\/span><br \/><span style=\"background:#FFFFFF;\">Compartiment cong\u00e9lation rapide<\/span><br \/><span style=\"background:#FFFFFF;\">2 tiroirs<\/span><br \/><span style=\"background:#FFFFFF;\">1 compartiment avec abattant<\/span><br \/><span style=\"background:#FFFFFF;\">1 bac \u00e0 gla\u00e7ons<\/span><br \/><br \/><span style=\"background:#FFFFFF;\">Pouvoir de cong\u00e9lation : 5 kg\/24h<\/span><br \/><span style=\"background:#FFFFFF;\">Autonomie en cas de coupure de courant :40 heures<\/span><br \/><span style=\"background:#FFFFFF;\">Classe climatique : SN-T<\/span><br \/><span style=\"background:#FFFFFF;\">Puissance nominale : 127 W<\/span><br \/><span style=\"background:#FFFFFF;\">Consommation d\u2019\u00e9nergie : 178 kWh\/an<\/span><br \/><span style=\"background:#FFFFFF;\">Niveau sonore : 37 dB(A)<\/span><\/span><p><\/p><p class=\"MsoNormal\" style=\"margin:0cm 0cm .0001pt;font-size:12pt;font-family:Calibri, sans-serif;\"><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);background:#FFFFFF;\">Double thermostat r\u00e9glable avec afficheur<\/span><span style=\"font-size:10.5pt;font-family:Arial, sans-serif;color:rgb(86,82,112);\"><br \/><span style=\"background:#FFFFFF;\">Alarme sonore temp\u00e9rature<\/span><br \/><span style=\"background:#FFFFFF;\">Cong\u00e9lation rapide<\/span><br \/><span style=\"background:#FFFFFF;\">Refroidissement rapide<\/span><br \/><br \/><span style=\"background:#FFFFFF;\">Pr\u00e9voir un d\u00e9port de porte, cot\u00e9 charni\u00e8res,<\/span><br \/><span style=\"background:#FFFFFF;\">de 25 cm pour l\u2019ouverture<\/span><\/span><span style=\"font-family:\u0027Times New Roman\u0027, serif;\"><\/span><\/p><p><\/p><p class=\"MsoNormal\" style=\"margin:0cm 0cm .0001pt;font-size:12pt;font-family:Calibri, sans-serif;\"><\/p><p>\u00a0<\/p><\/div>"},"description_short":{"1":"Disponibilit\u00e9s des pi\u00e8ces d\u00e9tach\u00e9es : 10 ans\nVolume totale : 331 L ( 234  97 )\nFroid ventil\u00e9\nDim HxLxP : 196.8 x 60 x 72,8 cm"},"quantity":"0","minimal_quantity":"1","low_stock_threshold":null,"low_stock_alert":"0","available_now":{"1":""},"available_later":{"1":"Non disponible"},"price":"1665.833333","specificPrice":0,"additional_shipping_cost":"0.00","wholesale_price":"1213.890000","on_sale":"0","online_only":"0","unity":"","unit_price":null,"unit_price_ratio":"0.000000","ecotax":"0.000000","reference":"FAB32RCR3","supplier_reference":"","location":"","width":"63.000000","height":"206.640000","depth":"76.440000","weight":"96.000000","ean13":"8017709250058","isbn":"","upc":"","link_rewrite":{"1":"refrigerateur-smeg-fab32rcr3-creme"},"meta_description":{"1":"Achetez votre FAB32RCR3 SMEG R\u00e9frig\u00e9rateur combin\u00e9  Beige \/ cr\u00e8me  \u2714 Garantie 5 ans OFFERTE \u2714 Livraison GRATUITE \u2714 Paiement en 3x ou 4x."},"meta_keywords":{"1":""},"meta_title":{"1":"FAB32RCR3 SMEG R\u00e9frig\u00e9rateur combin\u00e9 pas cher  \u2714\ufe0f Garantie 5 ans OFFERTE"},"quantity_discount":"0","customizable":"0","new":null,"uploadable_files":"0","text_fields":"0","active":"1","redirect_type":"301-category","id_type_redirected":"0","available_for_order":"0","available_date":"0000-00-00","show_condition":"0","condition":"new","show_price":"1","indexed":"0","visibility":"none","date_add":"2020-11-09 11:10:47","date_upd":"2023-01-31 16:52:18","tags":null,"state":"1","base_price":null,"id_tax_rules_group":"1","id_color_default":0,"advanced_stock_management":"0","out_of_stock":"2","depends_on_stock":null,"isFullyLoaded":false,"cache_is_pack":"0","cache_has_attachments":"0","is_virtual":"0","id_pack_product_attribute":null,"cache_default_attribute":"0","category":false,"pack_stock_type":"3","additional_delivery_times":"1","delivery_in_stock":{"1":"37"},"delivery_out_stock":{"1":""},"id":8229,"id_shop_list":[],"force_id":false},"lang":{"fr":"1"},"category":[{"id":"2","id_category":"2","name":"Accueil","active":"1","position":"0","description":"","id_parent":"1","id_category_default":null,"level_depth":"1","nleft":"2","nright":"1113","link_rewrite":"accueil","meta_title":"Accueil pas cher - Garantie 5 ans","meta_keywords":"","meta_description":"Retrouvez notre s\u00e9lection de Accueil durables et r\u00e9parables - Livraison et garantie 5 ans GRATUITES","date_add":"2019-02-04 18:27:11","date_upd":"2020-10-14 17:34:59","is_root_category":"1","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":"3","id_category":"3","name":"FROID","active":"1","position":"0","description":"<div class=\"wrapper_top\">\n<div class=\"content_scene_cat\">\n<div class=\"content_scene_cat_bg\">\n<div class=\"cat_desc\">\n<div id=\"category_description_short\" class=\"rte\">Belong est le seul site qui garantit vos r\u00e9frig\u00e9rateurs, cong\u00e9lateurs, frigos pendant 5 ans. Nous avons s\u00e9lectionn\u00e9 pour vous les meilleurs produits pour conserver au frais vos produits parmi les plus grandes marques telles que Samsung, Smeg, Siemens ou encore Liebherr. Achetez en toute s\u00e9r\u00e9nit\u00e9 votre r\u00e9frig\u00e9rateur, cong\u00e9lateur, caves \u00e0 vin ou encore des accessoires froid. Belong, le seul site d\u0027\u00e9lectrom\u00e9nager qui vous assure 5 ans de tranquillit\u00e9 !<\/div>\n<\/div>\n<\/div>\n<\/div>\n<\/div>","id_parent":"2","id_category_default":null,"level_depth":"2","nleft":"3","nright":"152","link_rewrite":"froid","meta_title":"FROID pas cher \u2714\ufe0f Garantie 5 ans OFFERTE","meta_keywords":"","meta_description":"Retrouvez nos produits de FROID pas chers \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x.","date_add":"2015-07-21 21:43:02","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":"179","id_category":"179","name":"R\u00e9frig\u00e9rateur","active":"1","position":"0","description":"<p class=\"MsoNormal\" style=\"text-align:left;\">Vous cherchez un r\u00e9frig\u00e9rateur pour votre cuisine ? Vous \u00eates au bon endroit ! D\u00e9couvrez notre large choix de <strong>r\u00e9frig\u00e9rateurs durables<\/strong> et garantis 5 ans, livraison gratuite incluse. Nous avons s\u00e9lectionn\u00e9 pour vous les meilleurs r\u00e9frig\u00e9rateurs pour conserver au frais vos produits parmi les plus grandes marques telles que <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1635-refrigerateur-samsung\">Samsung<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1683-refrigerateur-haier\">Haier<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1637-refrigerateur-lg\">Lg<\/a><\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/846-refrigerateur-liebherr\">Liebherr<\/a><\/span> ou encore <span style=\"text-decoration:underline;\">Bosch<\/span>. Attach\u00e9 \u00e0 l\u2019esth\u00e9tisme et aux fonctionnalit\u00e9s ? Vous trouverez \u00e0 coup s\u00fbr le frigo fait pour vous :\u00a0 <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/184-refrigerateur-combine\">r\u00e9frig\u00e9rateur combin\u00e9<\/a><\/span>\u00a0ou <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/181-refrigerateur-1-porte\">1 porte<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/185-refrigerateur-encastrable\">r\u00e9frig\u00e9rateur\u00a0encastrable<\/a><\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/183-refrigerateur-americain\">r\u00e9frig\u00e9rateur am\u00e9ricain<\/a><\/span> ou encore <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/224-refrigerateur-multi-portes\">multi portes<\/a><\/span>. Il peut \u00eatre de couleur <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/465-refrigerateur-retro-vintage-noir\">noir<\/a><\/span>, <span style=\"text-decoration:underline;\">rouge<\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/468-refrigerateur-retro-vintage-creme\">cr\u00e8me<\/a><\/span> ou alors au\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/451-refrigerateur-vintage\">design vintage<\/a><\/span>, tout est possible ! Achetez en toute s\u00e9r\u00e9nit\u00e9 votre frigo et ses\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/194-accessoires-froid\">accessoires<\/a><\/span> chez Belong, le seul site qui garantit tous ses produits 5 ans !<\/p>","id_parent":"3","id_category_default":null,"level_depth":"3","nleft":"4","nright":"65","link_rewrite":"refrigerateur","meta_title":"R\u00e9frig\u00e9rateur durable et r\u00e9parable - Garantie 5 ans OFFERTE \u267b\ufe0f","meta_keywords":"","meta_description":"Retrouvez notre s\u00e9lection de R\u00e9frig\u00e9rateur durables et r\u00e9parables \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x.","date_add":"2015-08-12 16:11:32","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":184,"id_category":"184","name":{"1":"R\u00e9frig\u00e9rateur combin\u00e9"},"active":"1","position":"1","description":{"1":"<p>Besoins d\u2019un r\u00e9frig\u00e9rateur combin\u00e9 pour votre cuisine ? D\u00e9couvrez notre large choix de <strong>frigos combin\u00e9s durables<\/strong> et garantis 5 ans, livraison gratuite incluse. Nous avons s\u00e9lectionn\u00e9 pour vous <strong>les meilleurs r\u00e9frig\u00e9rateurs combin\u00e9s<\/strong> pour conserver au frais vos produits parmi les plus grandes marques telles que <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1681-refrigerateur-combine-samsung\">Samsung<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/184-refrigerateur-combine\/s-8\/marque_2-miele\">Miele<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/184-refrigerateur-combine\/s-8\/marque_2-bosch\">Bosch<\/a><\/span>, ou encore <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/184-refrigerateur-combine\/s-8\/marque_2-smeg\">Smeg<\/a><\/span>. Envie d\u2019un produit design et fonctionnel ? Vous trouverez \u00e0 coup s\u00fbr le frigo cong\u00e9lateur fait pour vous :\u00a0 cong\u00e9lateur en haut, ou en bas, au\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/451-refrigerateur-retro-vintage-annees-50\">look vintage<\/a><\/span> ou encore des portes qui soit r\u00e9versible et une fabrique de gla\u00e7on. Achetez en toute s\u00e9r\u00e9nit\u00e9 votre r\u00e9frig\u00e9rateur combin\u00e9 et <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/194-accessoires-froid\">ses\u00a0accessoires<\/a><\/span> chez Belong, le seul site qui garantit tous ses produits 5 ans !<strong><\/strong><\/p>"},"id_parent":"179","id_category_default":null,"level_depth":"4","nleft":"7","nright":"12","link_rewrite":{"1":"refrigerateur-combine"},"meta_title":{"1":"R\u00e9frig\u00e9rateur combin\u00e9 pas cher \u2714\ufe0f Garantie 5 ans OFFERTE"},"meta_keywords":{"1":""},"meta_description":{"1":"Retrouvez notre s\u00e9lection de R\u00e9frig\u00e9rateur combin\u00e9 pas chers \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x."},"date_add":"2015-08-12 16:12:46","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":184,"id_shop_list":[],"force_id":false},{"id":"2","id_category":"2","name":"Accueil","active":"1","position":"0","description":"","id_parent":"1","id_category_default":null,"level_depth":"1","nleft":"2","nright":"1113","link_rewrite":"accueil","meta_title":"Accueil pas cher - Garantie 5 ans","meta_keywords":"","meta_description":"Retrouvez notre s\u00e9lection de Accueil durables et r\u00e9parables - Livraison et garantie 5 ans GRATUITES","date_add":"2019-02-04 18:27:11","date_upd":"2020-10-14 17:34:59","is_root_category":"1","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":"3","id_category":"3","name":"FROID","active":"1","position":"0","description":"<div class=\"wrapper_top\">\n<div class=\"content_scene_cat\">\n<div class=\"content_scene_cat_bg\">\n<div class=\"cat_desc\">\n<div id=\"category_description_short\" class=\"rte\">Belong est le seul site qui garantit vos r\u00e9frig\u00e9rateurs, cong\u00e9lateurs, frigos pendant 5 ans. Nous avons s\u00e9lectionn\u00e9 pour vous les meilleurs produits pour conserver au frais vos produits parmi les plus grandes marques telles que Samsung, Smeg, Siemens ou encore Liebherr. Achetez en toute s\u00e9r\u00e9nit\u00e9 votre r\u00e9frig\u00e9rateur, cong\u00e9lateur, caves \u00e0 vin ou encore des accessoires froid. Belong, le seul site d\u0027\u00e9lectrom\u00e9nager qui vous assure 5 ans de tranquillit\u00e9 !<\/div>\n<\/div>\n<\/div>\n<\/div>\n<\/div>","id_parent":"2","id_category_default":null,"level_depth":"2","nleft":"3","nright":"152","link_rewrite":"froid","meta_title":"FROID pas cher \u2714\ufe0f Garantie 5 ans OFFERTE","meta_keywords":"","meta_description":"Retrouvez nos produits de FROID pas chers \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x.","date_add":"2015-07-21 21:43:02","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":"179","id_category":"179","name":"R\u00e9frig\u00e9rateur","active":"1","position":"0","description":"<p class=\"MsoNormal\" style=\"text-align:left;\">Vous cherchez un r\u00e9frig\u00e9rateur pour votre cuisine ? Vous \u00eates au bon endroit ! D\u00e9couvrez notre large choix de <strong>r\u00e9frig\u00e9rateurs durables<\/strong> et garantis 5 ans, livraison gratuite incluse. Nous avons s\u00e9lectionn\u00e9 pour vous les meilleurs r\u00e9frig\u00e9rateurs pour conserver au frais vos produits parmi les plus grandes marques telles que <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1635-refrigerateur-samsung\">Samsung<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1683-refrigerateur-haier\">Haier<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1637-refrigerateur-lg\">Lg<\/a><\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/846-refrigerateur-liebherr\">Liebherr<\/a><\/span> ou encore <span style=\"text-decoration:underline;\">Bosch<\/span>. Attach\u00e9 \u00e0 l\u2019esth\u00e9tisme et aux fonctionnalit\u00e9s ? Vous trouverez \u00e0 coup s\u00fbr le frigo fait pour vous :\u00a0 <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/184-refrigerateur-combine\">r\u00e9frig\u00e9rateur combin\u00e9<\/a><\/span>\u00a0ou <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/181-refrigerateur-1-porte\">1 porte<\/a><\/span>, <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/185-refrigerateur-encastrable\">r\u00e9frig\u00e9rateur\u00a0encastrable<\/a><\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/183-refrigerateur-americain\">r\u00e9frig\u00e9rateur am\u00e9ricain<\/a><\/span> ou encore <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/224-refrigerateur-multi-portes\">multi portes<\/a><\/span>. Il peut \u00eatre de couleur <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/465-refrigerateur-retro-vintage-noir\">noir<\/a><\/span>, <span style=\"text-decoration:underline;\">rouge<\/span>,\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/468-refrigerateur-retro-vintage-creme\">cr\u00e8me<\/a><\/span> ou alors au\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/451-refrigerateur-vintage\">design vintage<\/a><\/span>, tout est possible ! Achetez en toute s\u00e9r\u00e9nit\u00e9 votre frigo et ses\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/194-accessoires-froid\">accessoires<\/a><\/span> chez Belong, le seul site qui garantit tous ses produits 5 ans !<\/p>","id_parent":"3","id_category_default":null,"level_depth":"3","nleft":"4","nright":"65","link_rewrite":"refrigerateur","meta_title":"R\u00e9frig\u00e9rateur durable et r\u00e9parable - Garantie 5 ans OFFERTE \u267b\ufe0f","meta_keywords":"","meta_description":"Retrouvez notre s\u00e9lection de R\u00e9frig\u00e9rateur durables et r\u00e9parables \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x.","date_add":"2015-08-12 16:11:32","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":"1645","id_category":"1645","name":"R\u00e9frig\u00e9rateurs par marques","active":"1","position":"12","description":"","id_parent":"179","id_category_default":null,"level_depth":"4","nleft":"37","nright":"64","link_rewrite":"refrigerateurs-par-marques","meta_title":"","meta_keywords":"","meta_description":"","date_add":"2021-03-18 16:08:11","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":false,"id_shop_list":[],"force_id":false},{"id":1625,"id_category":"1625","name":{"1":"R\u00e9frig\u00e9rateur SMEG"},"active":"1","position":"1","description":{"1":"<p class=\"MsoNormal\" style=\"text-align:justify;\">Vous avez besoin d\u2019un nouveau r\u00e9frig\u00e9rateur pour votre cuisine ? Pourquoi ne pas choisir un r\u00e9frig\u00e9rateur de la marque Smeg qui propose de l\u2019\u00e9lectrom\u00e9nager de haute qualit\u00e9. Sur Belong, vous trouverez notre s\u00e9lection de <b>r\u00e9frig\u00e9rateurs Smeg durables<\/b> garantis 5 ans gr\u00e2ce \u00e0 notre indice de durabilit\u00e9 qui assure un produit de qualit\u00e9 ainsi qu\u2019une livraison offerte sur l\u2019ensemble de nos <b>frigos Smeg<\/b>. Smeg propose une vaste gamme de r\u00e9frig\u00e9rateurs avec par exemple des <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/463-refrigerateur-retro-vintage-smeg\">r\u00e9frig\u00e9rateurs vintages Smeg<\/a><\/span>.<\/p>\n<p><\/p>\n<p class=\"MsoNormal\" style=\"text-align:justify;\">Si vous voulez ajouter une touche de couleur \u00e0 votre cuisine retrouvez les r\u00e9frig\u00e9rateurs r\u00e9tros de couleur rouge, bleu, ou <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/468-refrigerateur-retro-vintage-creme\">cr\u00e8me<\/a><\/span>\u00a0qu\u2019ils soient combin\u00e9s ou 1 porte. Achetez en toute s\u00e9r\u00e9nit\u00e9 votre r\u00e9frig\u00e9rateur Smeg chez Belong, le seul site qui garantit tous ses produits 5 ans !<\/p>"},"id_parent":"1645","id_category_default":null,"level_depth":"5","nleft":"40","nright":"41","link_rewrite":{"1":"refrigerateur-smeg"},"meta_title":{"1":"R\u00e9frig\u00e9rateur Smeg pas cher \u2714\ufe0f Garantie 5 ans OFFERTE | Belong"},"meta_keywords":{"1":""},"meta_description":{"1":"Retrouvez notre s\u00e9lection de r\u00e9frig\u00e9rateur Smeg pas chers \u2714\ufe0f Garantie 5 ans OFFERTE\u2714\ufe0f Livraison GRATUITE\u2714\ufe0f Paiement en 3x ou 4x."},"date_add":"2021-02-11 16:14:07","date_upd":"2023-01-31 11:04:32","is_root_category":"0","id_shop_default":"1","groupBox":null,"doNotRegenerateNTree":false,"id_image":1625,"id_shop_list":[],"force_id":false}],"image":{"8229-142780":"https:\/\/cegidpim.belong.fr\/img\/p\/1\/4\/2\/7\/8\/0\/142780.jpg","8229-142781":"https:\/\/cegidpim.belong.fr\/img\/p\/1\/4\/2\/7\/8\/1\/142781.jpg","8229-142782":"https:\/\/cegidpim.belong.fr\/img\/p\/1\/4\/2\/7\/8\/2\/142782.jpg"},"decli":0,"carac":{"feature":[{"name":{"1":"Consommation annuelle en \u00e9nergie (en kWh)"},"position":"4","id":20,"id_shop_list":[],"force_id":false},{"name":{"1":"Couleur"},"position":"2","id":21,"id_shop_list":[],"force_id":false},{"name":{"1":"Distributeur d\u0027eau"},"position":"37","id":25,"id_shop_list":[],"force_id":false},{"name":{"1":"Classe Energ\u00e9tique"},"position":"3","id":27,"id_shop_list":[],"force_id":false},{"name":{"1":"Fabrique de gla\u00e7ons"},"position":"38","id":29,"id_shop_list":[],"force_id":false},{"name":{"1":"Hauteur (en cm)"},"position":"91","id":30,"id_shop_list":[],"force_id":false},{"name":{"1":"Largeur (en cm)"},"position":"93","id":32,"id_shop_list":[],"force_id":false},{"name":{"1":"Poids Net (en kg)"},"position":"97","id":49,"id_shop_list":[],"force_id":false},{"name":{"1":"Porte(s) r\u00e9versible(s)"},"position":"5","id":50,"id_shop_list":[],"force_id":false},{"name":{"1":"Profondeur (en cm)"},"position":"95","id":53,"id_shop_list":[],"force_id":false},{"name":{"1":"Type de froid cong\u00e9lateur"},"position":"34","id":72,"id_shop_list":[],"force_id":false},{"name":{"1":"Type de froid r\u00e9frig\u00e9rateur"},"position":"7","id":73,"id_shop_list":[],"force_id":false},{"name":{"1":"Type de pose"},"position":"1","id":77,"id_shop_list":[],"force_id":false},{"name":{"1":"Volume utile cong\u00e9lateur (en litres)"},"position":"35","id":83,"id_shop_list":[],"force_id":false},{"name":{"1":"Volume utile r\u00e9frig\u00e9rateur (en litres)"},"position":"8","id":84,"id_shop_list":[],"force_id":false},{"name":{"1":"Volume utile total (en litres)"},"position":"6","id":85,"id_shop_list":[],"force_id":false},{"name":{"1":"Garantie"},"position":"100","id":111,"id_shop_list":[],"force_id":false},{"name":{"1":"Disponibilit\u00e9 Pi\u00e8ces d\u00e9tach\u00e9es (En ann\u00e9es)"},"position":"0","id":115,"id_shop_list":[],"force_id":false},{"name":{"1":"Type de d\u00e9givrage cong\u00e9lateur"},"position":"36","id":128,"id_shop_list":[],"force_id":false},{"name":{"1":"Indice de durabilit\u00e9"},"position":"99","id":169,"id_shop_list":[],"force_id":false},{"name":{"1":"Disponible vente"},"position":"102","id":173,"id_shop_list":[],"force_id":false},{"name":{"1":"Maillage MG5"},"position":"103","id":174,"id_shop_list":[],"force_id":false},{"name":{"1":"ODR"},"position":"110","id":183,"id_shop_list":[],"force_id":false},{"name":{"1":"Mapping mode de livraison"},"position":"219","id":293,"id_shop_list":[],"force_id":false}],"value":[{"id_feature":"20","value":{"1":"178"},"custom":"0","id":218370,"id_shop_list":[],"force_id":false},{"id_feature":"21","value":{"1":"Beige \/ cr\u00e8me"},"custom":"0","id":5334,"id_shop_list":[],"force_id":false},{"id_feature":"25","value":{"1":"Non"},"custom":"0","id":85,"id_shop_list":[],"force_id":false},{"id_feature":"27","value":{"1":"D"},"custom":"0","id":101,"id_shop_list":[],"force_id":false},{"id_feature":"29","value":{"1":"Non"},"custom":"0","id":105,"id_shop_list":[],"force_id":false},{"id_feature":"30","value":{"1":"196,8"},"custom":"0","id":219022,"id_shop_list":[],"force_id":false},{"id_feature":"32","value":{"1":"60"},"custom":"0","id":113648,"id_shop_list":[],"force_id":false},{"id_feature":"49","value":{"1":"96,6"},"custom":"0","id":225002,"id_shop_list":[],"force_id":false},{"id_feature":"50","value":{"1":"Non"},"custom":"0","id":116,"id_shop_list":[],"force_id":false},{"id_feature":"53","value":{"1":"72,8"},"custom":"0","id":225000,"id_shop_list":[],"force_id":false},{"id_feature":"72","value":{"1":"Froid ventil\u00e9"},"custom":"0","id":149,"id_shop_list":[],"force_id":false},{"id_feature":"73","value":{"1":"Froid ventil\u00e9"},"custom":"0","id":155,"id_shop_list":[],"force_id":false},{"id_feature":"77","value":{"1":"Pose libre"},"custom":"0","id":171,"id_shop_list":[],"force_id":false},{"id_feature":"83","value":{"1":"96"},"custom":"0","id":225001,"id_shop_list":[],"force_id":false},{"id_feature":"84","value":{"1":"234"},"custom":"0","id":218371,"id_shop_list":[],"force_id":false},{"id_feature":"85","value":{"1":"331"},"custom":"0","id":217043,"id_shop_list":[],"force_id":false},{"id_feature":"111","value":{"1":"5 ans"},"custom":"0","id":1001,"id_shop_list":[],"force_id":false},{"id_feature":"115","value":{"1":"10"},"custom":"0","id":6713,"id_shop_list":[],"force_id":false},{"id_feature":"128","value":{"1":"Automatique"},"custom":"0","id":23171,"id_shop_list":[],"force_id":false},{"id_feature":"169","value":{"1":"88"},"custom":"0","id":218684,"id_shop_list":[],"force_id":false},{"id_feature":"173","value":{"1":"Non"},"custom":"0","id":219248,"id_shop_list":[],"force_id":false},{"id_feature":"174","value":{"1":"EXPLO"},"custom":"0","id":223628,"id_shop_list":[],"force_id":false},{"id_feature":"183","value":{"1":"UN SET DE 3 BOITES HERM\u00c9TIQUES offert jusqu\u0027au 31 Octobre"},"custom":"0","id":224738,"id_shop_list":[],"force_id":false},{"id_feature":"293","value":{"1":"GEM"},"custom":"0","id":228136,"id_shop_list":[],"force_id":false}]},"manufacturer":{"id":10,"name":"SMEG","description":{"1":"<h2>La gamme de produits r\u00e9tro Smeg<\/h2>\n<p>En 1997 Smeg cr\u00e9\u00e9e la gamme de <strong>r\u00e9frig\u00e9rateurs iconiques FAB<\/strong>. Il s\u2019agit de r\u00e9frig\u00e9rateur avec des formes arrondies des poign\u00e9es chrom\u00e9es ainsi qu\u2019un grand panel de couleurs vives ou pastel. Ces r\u00e9frig\u00e9rateurs vous permettent de personnaliser un objet du quotidien pour qu\u2019il devienne un vrai objet de d\u00e9coration. Toutes ces caract\u00e9ristiques donnent au r\u00e9frig\u00e9rateur de la gamme FAB un look vintage ann\u00e9es 50 qui est tr\u00e8s \u00e0 la mode de nos jours. De plus, ces r\u00e9frig\u00e9rateurs sont disponibles en de multiples couleurs comme par exemple rouge,\u00a0bleu ou encore\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/468-refrigerateur-retro-vintage-creme\">cr\u00e8me<\/a><\/span> ; et ils sont surtout disponibles en sept tailles diff\u00e9rentes pour qu\u2019ils puissent convenir \u00e0 toutes les cuisines.<\/p>\n<p>Mais les r\u00e9frig\u00e9rateurs ne sont pas les seuls produits de la marque qui poss\u00e8de un <strong>look r\u00e9tro<\/strong> vous, pourrez aussi retrouver d\u2019autres appareils \u00e9lectrom\u00e9nagers dans le m\u00eame style. Si vous souhaitez harmoniser l\u2019ensemble de votre cuisine retrouv\u00e9 les <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1009-hotte-smeg\">hottes aspirantes<\/a><\/span>, les <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1771-lave-linge-smeg\">lave-linge<\/a><\/span>, les\u00a0<a href=\"https:\/\/www.belong.fr\/1008-lave-vaisselle-smeg\"><span style=\"text-decoration:underline;\">lave-vaisselle<\/span>s<\/a> ainsi que le petit \u00e9lectrom\u00e9nager avec un style ann\u00e9es 50 et une multitude de couleur.<\/p>\n<p>Bien que tous ces produits aient un design, ils ne sont pas d\u00e9pass\u00e9s pour autant ! Les produits de la marque Smeg poss\u00e8dent les derni\u00e8res technologies comme le froid ventil\u00e9 total et une zone <strong>\u00ab extra fresh 0\u00b0C \u00bb<\/strong> pour les r\u00e9frig\u00e9rateurs. Pour les lave-vaisselles, on retrouve le d\u00e9part diff\u00e9r\u00e9, le moteur <strong>\u00ab inverter \u00bb<\/strong> qui garantit une plus grande dur\u00e9e de vie ainsi que l\u2019option <strong>\u00ab enersave \u00bb<\/strong> qui entrouvre automatiquement la porte du lave-vaisselle une fois le cycle fini pour un s\u00e9chage optimal.<\/p>\n<p><\/p>\n<h3>Smeg une marque engag\u00e9 pour l\u2019environnement<\/h3>\n<p>Smeg est aussi une marque qui est investie pour l\u2019environnement depuis plusieurs ann\u00e9es. On peut retrouver cet engagement avec leur si\u00e8ge en Italie qui est un des sites les plus innovants en mati\u00e8re de consommation intelligente et de d\u00e9veloppement durable. Il a notamment remport\u00e9 un concours dans le cadre de la \u00ab semaine de la Bio-architecture \u00bb.<\/p>\n<p>De plus, l\u2019ensemble des produits de la marque sont con\u00e7us en tenant compte des <strong>consid\u00e9rations environnementales<\/strong>. Et cela notamment gr\u00e2ce au choix des mat\u00e9riaux qui sont moins nocifs pour l\u2019environnement et surtout qui sont recyclables tels que l\u0027acier, le verre, l\u0027aluminium et le laiton.<\/p>\n<p>Smeg respecte bien sur l\u2019ensemble des directives europ\u00e9ennes comme le RoHS (Restriction of hazardous substances in electrical and electronic equipment) ainsi de le REACH (Registration, Evaluation, Authorization and Restriction of Chemical Substances). Ces deux directives visent \u00e0 supprimer l\u2019utilisation de substances dangereuses pour l\u2019Homme ou l\u2019environnement pour l\u2019ensemble des produits \u00e9lectrom\u00e9nagers.<\/p>\n<p>Mais Smeg va au-del\u00e0 des normes pour ces produits en s\u2019effor\u00e7ant de concevoir des produits avec une <strong>faible consommation d\u2019\u00e9nergie<\/strong>, ce qui vous permet de choisir un produit avec un design de qualit\u00e9 qui respecte l\u2019environnement.\u00a0<\/p>\n<p><\/p>\n<h3>L\u2019\u00e9lectrom\u00e9nager Smeg<\/h3>\n<p><\/p>\n<p>Smeg est une entreprise cr\u00e9\u00e9e en 1948 par Vittorio Bertazzon en Italie. L\u2019entreprise \u00e0 sa cr\u00e9ation \u00e9tait d\u00e9di\u00e9e au travail du m\u00e9tal et de l\u2019\u00e9mail ce qui explique l\u2019acronyme de Smeg (Smalterie Metallurgiche Emiliane Guastalla). C\u2019est finalement \u00e0 partir des ann\u00e9es 1955 que Smeg s\u2019est lanc\u00e9 dans la production de <strong>centre de cuisson<\/strong> et de <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1624-gaziniere-smeg\">gazini\u00e8re<\/a><\/span>. Par la suite elle s\u2019est diversifi\u00e9e dans le gros \u00e9lectrom\u00e9nager puis le petit \u00e9lectrom\u00e9nager. Le slogan de la marque est \u00ab Design &amp; Technologie \u00bb, en effet la marque met un point d\u2019honneur sur la qualit\u00e9 des produits qu\u2019elles proposent pour qu\u2019ils puissent \u00eatre fiables et robustes dans le temps. Le design des produits chez Smeg est tr\u00e8s important et il propose beaucoup de solutions pour pouvoir <strong>personnaliser votre cuisine<\/strong> selon vos envies.<\/p>\n<h3>Smeg un partenaire de confiance<\/h3>\n<p>Nous travaillons depuis plusieurs ann\u00e9es avec Smeg pour vous fournir des produits de qualit\u00e9 au meilleur prix. Avec ma garantie5ans.fr, nous vous garantissons 5 ans de tranquillit\u00e9 pour l\u2019achat d\u2019un produit de la marque.<\/p>"},"short_description":{"1":"<p>Smeg est l\u2019une des marques les plus c\u00e9l\u00e8bres gr\u00e2ce \u00e0 des produits avec un <strong>look vintage<\/strong> tout en mettant l\u2019accent sur <strong>le design<\/strong> ce qui est rare pour une marque d\u2019\u00e9lectrom\u00e9nager. La marque vous permettra de personnaliser votre cuisine tout en y ajoutant de la couleur. Fini les frigos noirs ou blancs, placent au choix avec des plus d\u2019une dizaine de coloris diff\u00e9rents.<\/p>\n<p>Si vous \u00eates attach\u00e9e au design des produits et que vous souhaitez vous diff\u00e9rencier Smeg est la marque qu\u2019il vous faut ! Si vous n\u2019\u00eates pas fan de style r\u00e9tro pas de probl\u00e8me, vous pouvez toujours choisir des produits de la marque plus sobre et classique ou m\u00eame les derni\u00e8res nouveaut\u00e9s de la marque avec un style plus raffin\u00e9 avec la gamme <strong>\u00ab Dolce Stil Novo \u00bb<\/strong>.<\/p>\n<p>Sur Belong retrouver l\u2019ensemble des produits Smeg comme les <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/451-refrigerateur-retro-vintage-annees-50\">r\u00e9frig\u00e9rateurs vintage Smeg<\/a><\/span>, les\u00a0<span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1095-piano-de-cuisson-smeg\">pianos de cuisson Smeg<\/a><\/span> ou encore les <span style=\"text-decoration:underline;\"><a href=\"https:\/\/www.belong.fr\/1007-four-smeg\">fours Smeg<\/a><\/span>. Et cela au meilleur prix et garanti 5 ans sur l\u2019ensemble des produits pour vous permettre d\u2019acheter en toute s\u00e9r\u00e9nit\u00e9.<\/p>"},"id_address":null,"date_add":"2015-08-13 15:53:58","date_upd":"2022-06-14 16:59:34","link_rewrite":"smeg","meta_title":{"1":"SMEG - Frigo vintage, Piano de cuisson r\u00e9tro pas cher \u2714\ufe0f"},"meta_keywords":{"1":"\u00e9lectrom\u00e9nager,r\u00e9frig\u00e9rateur,frigo,r\u00e9frig\u00e9rateur combin\u00e9,frigo combin\u00e9,r\u00e9frig\u00e9rateur am\u00e9ricain,frigo am\u00e9ricain,r\u00e9frig\u00e9rateur deux portes,r\u00e9frig\u00e9rateur une porte,frigo deux portes,frigo une porte,cong\u00e9lateur,cong\u00e9lateur encastrable,frigo encastrable,table"},"meta_description":{"1":"D\u00e9couvrez les meilleurs produits de la marque SMEG pas chers sur Belong - Garantie 5 ans incluse \u2013 Livraison offerte \u2013 Paiement en 3x ou 4x"},"active":"1","id_shop_list":[],"force_id":false}}');

//        $langPS = json_decode(json_encode($json->lang), true);
        $langPS = $json->lang ?? 1;
//        $categ = $json->category ?? 0;
//        $image = $json->image ?? 0;
//        $carac = $json->carac ?? 0;
//        $marque = $json->vendor ?? 0;
//        $decli = $json->decli ?? 0;


        // Verif si déjà crossid
        $idPim = Outils::getExist($prod->id, $id_diffusion, 'crossid', 'product');
        if ($idPim > 0) {
            return 'PS ' . $prod->id . ' - OK by ID ' . $idPim;
        }

        // Verif si EAN13-
        if (strlen($json['variants'][0]['barcode']) == 13) {
            $idPim = Outils::getExist($prod->barcode, $id_diffusion, 'ean13', 'product');
            $diff = $diffusion;
            if ($idPim > 0) {
                // $obj = DataObject::getById($idPim);
                // Outils::getExist($obj, $diff, $prod->id, false);
                return 'PS ' . $prod->id . ' - OK by EAN13 ' . $idPim;
            }
        }

        $tabCateg = array();
        foreach ($categ as $json_category) {
            $idPim = Outils::getExist(
                search: $json_category->id_category,
                source: $diffusion->getID(),
                objet: 'category'
            );

            if ($idPim > 0) {
                $category = Category::getById(id: $idPim);
            } else {
                $category = Outils::putCreateCategory(
                    categ: $json_category,
                    diffusion: $diffusion,
                    parent: $diffusion->getId_folder(),
                    langPS: $langPS
                );
//                $category = $this->createCategory(categ: $json, diffusion: $diffusion, parent: $diffusion->getId_folder(), langPS: $langPS);
            }
            $tabCateg[] = $category;
        }
        $categList = array_unique($tabCateg);
        $caracList = 0;
        if ($carac) {
//            $caracList = Outils::putCreateCarac(carac: $carac, diffusion: $diffusion, langPS: $langPS);
            $caracList = $this->pullCarac($carac, $diffusion, $langPS);

//            foreach ($carac->feature as $k => $json) {
//                $idCarac = Outils::getExist($json->id, $diffusion->getID(), 'crossid', 'carac');
//
//                if ($idCarac == 0) {
//                    $idCarac = Outils::putCreateCarac($json, $diffusion, $langPS);
//                }
//
//                $idPim = Outils::putCreateCaracValue($carac->value[$k], $diffusion, $idCarac, $langPS);
//                $caracList[] = DataObject::getById($idPim);
//            }
        }

        if ($marque) {
//            $marqueList = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
//            $marqueList = $this->pullMarque($marque, $diffusion, $langPS);
            $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');
            if ($idMarq == 0) {
//            $idMarq = $this->createMarque($marque, $diffusion, $langPS);
                $idMarq = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
            }
            $marqueList = DataObject::getById(id: $idMarq);
        } else {
            $marqueList = 0;
        }

        if ($image) {
            $imageList = Outils::putImage($image);
        } else {
            $imageList = 0;
        }

        $decliList = 0;
        if ($decli) {
            $decliList = $this->pullDecli($decli, $diffusion, $langPS);
//            $decliList = Outils::putCreateDeclinaison(decli: $decli, diffusion: $diffusion, langPS: $langPS);
        } else {
            $decliList = 0;
        }

//        exit();
        //        $idProd = $prestashop_controller->pullProduct($prod, $diffusion, $categList, $caracList, $marqueList, $imageList, $decliList, $langPS);

        return Outils::putCreateProduct(
            prod: $prod,
            diffusion: $diffusion,
            categList: $categList,
            caracList: $caracList,
            marqueList: $marqueList,
            imageList: $imageList,
            decliList: $decliList,
            langPS: $langPS
        );


    }

    public function pullDecli($declis, $diffusion, $langPS)
    {
        $tabDecli = array();
        foreach ($declis->attribute as $idpa => $lists) {

            $tabAssoc = array();
            $idDeclinaison = 0;
            foreach ($lists as $k => $json) {
                $idAttribut = Outils::getExist($json->id, $diffusion->getID(), 'crossid', 'attribut');
                if ($idAttribut == 0) {
                    $idAttribut = Outils::putCreateAttribute($json, $diffusion, $langPS);
                }

                $idAttributValue = Outils::getExist($declis->value->{$idpa}[$k]->id, $diffusion->getID(), 'crossid', 'attributValue');
                if ($idAttributValue == 0) {
                    $idAttributValue = Outils::putCreateAttributeValue($declis->value->{$idpa}[$k], $diffusion, $idAttribut, $langPS);
                }
                $tabAssoc[] = DataObject::getById($idAttributValue);
            }

            if (count($tabAssoc)) {
                $idDeclinaison = Outils::getExist($declis->info->$idpa->id, $diffusion->getID(), 'crossid', 'declinaison');
                if ($idDeclinaison == 0) {
                    $idDeclinaison = Outils::putCreateDeclinaison($declis->info->$idpa, $diffusion, $lists, $tabAssoc, $langPS);
                }
                $tabDecli[$declis->info->$idpa->id] = DataObject::getById($idDeclinaison);
            }
        }
        return $tabDecli;
    }

    public function createMarque($marque, $diffusion, $langPS)
    {
        $id_parent = $this->folder['folderMarque'];
        $languages = $this->languages;

        $newMarq = new DataObject\Marque();
        $newMarq->setKey('PS_' . $marque->id);
        $newMarq->setPublished(true);
        $newMarq->setParentId($id_parent);
        foreach ($languages as $lang) {
            $nn = $langPS[$lang] ?? $langPS['fr'];
            $newMarq->setName($marque->name->$nn ?? $marque->name, $lang);
            $newMarq->setDescription($marque->description->$nn ?? $marque->description, $lang);
            $newMarq->setMeta_keyword($marque->meta_keywords->$nn ?? $marque->meta_keywords, $lang);
            $newMarq->setMeta_title($marque->meta_title->$nn ?? $marque->meta_title, $lang);
            $newMarq->setLink_rewrite($marque->link_rewrite->$nn ?? $marque->link_rewrite, $lang);
        }
        $newMarq->nohook = true;
        $newMarq->save();

        Outils::addCrossid($newMarq, $diffusion, $marque->id, false);
        return $newMarq->getId();
    }

    public function pullMarque($marque, $diffusion, $langPS)
    {
        $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');
        if ($idMarq == 0) {
//            $idMarq = $this->createMarque($marque, $diffusion, $langPS);
            $idMarq = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
        }
        return DataObject::getById(id: $idMarq);
    }

    public function pullCarac($caracs, $diffusion, $langPS)
    {
        $caracList = array();
//        $langPS = 1;
        foreach ($caracs->feature as $k => $json) {
            $idCarac = Outils::getExist($json->id, $diffusion->getID(), 'crossid', 'carac');

            if ($idCarac == 0) {
//                $idCarac = $this->createCarac($json, $diffusion, $langPS);
                $idCarac = Outils::putCreateCarac($json, $diffusion, $langPS);
            }

//            $idPim = Outils::getExist($caracs->value[$k]->id, $diffusion->getID(), 'crossid', 'caracValue');

//            if ($idPim == 0) {
//            $idPim = $this->createCaracValue($caracs->value[$k], $diffusion, $idCarac, $langPS);
            $idPim = Outils::putCreateCaracValue($caracs->value[$k], $diffusion, $idCarac, $langPS);
//            }

            $caracList[] = DataObject::getById($idPim);
        }
        return $caracList;
    }

    public function createCaracValue($carac, $diffusion, $id_parent, $langPS)
    {
        $carac_in_pim = DataObject\CaracValue::getByPath('/Carac/Shopify_' . $carac->id_feature . '/SHOPIFY_' . $carac->id_feature . '_value');
        if ($carac_in_pim) {
            return $carac_in_pim->getId();
        }
        $newCarac = new DataObject\CaracValue();
        $newCarac->setKey('SHOPIFY_' . $carac->id_feature . '_value');
        $newCarac->setPublished(true);
        $newCarac->setParentId($id_parent);
        $newCarac->setName($carac->name);

        $newCarac->nohook = true;
        $newCarac->save();
//        Outils::addCrossid(object: $newCarac, source:  $diffusion);

        return $newCarac->getId();
    }

    public function createCarac($carac, $diffusion, $langPS)
    {
        $id_parent = WebsiteSetting::getByName('folderCarac')->getData();;
        $newCarac = new DataObject\Carac();
        $newCarac->setKey('Shopify_' . $carac->id);
        $newCarac->setPublished(true);
        $newCarac->setParentId($id_parent);

        $newCarac->setName(name: $carac->name);
        $newCarac->nohook = true;
        $newCarac->save();

        Outils::addCrossid($newCarac, $diffusion, $carac->id, false);
        return $newCarac->getId();
    }

    /**
     * @throws \Exception
     */
    public function createCategory($categ, $diffusion, $parent, $langPS)
    {

        $fopa = Diffusion::getByPath('/Diffusion/Shopify')->getId();
        // if ($diffusion->getGlobal_diffusion()) {
        //     $fopa = $diffusion->getId_folder();
        // } else {
        //     $fopa = WebsiteSetting::getByName('folderCategoryGlobal')->getData();
        // }

        if ($parent > 0) {
            $id_parent = Outils::getExist($parent, $diffusion->getID(), 'crossid', 'category');
            if (!$id_parent) {
                $id_parent = $fopa;
            }
        } else {
            $id_parent = $fopa;
        }

        $languages = ['fr' => 1];

        $newCateg = new DataObject\Category();
        $newCateg->setKey('Shopify_' . $categ->id_category);
        $newCateg->setPublished(true);
        $newCateg->setParentId($id_parent);
        $newCateg->setName($categ->name, 'fr');
        $newCateg->setName($categ->name, 'en');
        $newCateg->setName($categ->name, 'de');
        $newCateg->setDescription($categ->description);
//        $newCateg->setMeta_keyword($categ->meta_keywords);
//        $newCateg->setMeta_title($categ->meta_title);
        $newCateg->setLink_rewrite($categ->link_rewrite, 'fr');
        $newCateg->setLink_rewrite($categ->link_rewrite, 'en');
        $newCateg->setLink_rewrite($categ->link_rewrite, 'de');
        $newCateg->nohook = true;
        $newCateg->save();

        Outils::addCrossid($newCateg, $diffusion, $categ->id, false);
        return $newCateg;
    }

    public function createProductActionOld()
    {
        $shopify_product = new Product();

        $data = [
            "prod" => [
                "tax_name" => null,
                "tax_rate" => null,
                "id_manufacturer" => "10",
                "id_supplier" => "0",
                "id_category_default" => "184",
                "id_shop_default" => "1",
                "manufacturer_name" => $shopify_product->vendor,
                "supplier_name" => null,
                "name" => [
                    "1" => $shopify_product->title
                ],
                "description" => [
                    "1" => $shopify_product->body_html
                ],
                "description_short" => [
                    "1" => $shopify_product->body_html
                ],
                "quantity" => $shopify_product->variants[0]->inventory_quantity,
                "price" => $shopify_product->variants[0]->price,
                "wholesale_price" => $shopify_product->variants[0]->price,
                "ecotax" => "0.000000",
                "reference" => $shopify_product->variants[0]->sku,
                "supplier_reference" => null,
                "width" => null,
                "height" => $shopify_product->variants[0]->weight,
                "depth" => null,
                "weight" => $shopify_product->variants[0]->weight,
//                TODO : Distinguer les ean13, isbn et upc
                "ean13" => strlen($shopify_product->variants[0]->barcode) === 13 ? $shopify_product->variants[0]->barcode : '1111111111111',
//                "isbn" => null,
//                "upc" => null,
                "link_rewrite" => [
                    "1" => $shopify_product->handle
                ],
//                TODO : Gérer les meta
//                "meta_description" => [
//                    "1" => "Achetez votre produit."
//                ],
//                "meta_keywords" => [
//                    "1" => ""
//                ],
//                "meta_title" => [
//                    "1" => "super produit"
//                ],
                "active" => $shopify_product->status == 'active' ? "1" : "0",
                "id_tax_rules_group" => null,
            ],
            "lang" => [
                "fr" => "1"
            ],
//            "category" => [
//                [
//                    "id" => "2",
//                    "id_category" => "2",
//                    "name" => "Accueil",
//                    "active" => "1",
//                    "description" => "",
//                    "id_parent" => "1",
//                    "link_rewrite" => "accueil",
//                    "meta_title" => "Accueil pas cher",
//                    "meta_keywords" => "",
//                    "meta_description" => "Retrouvez notre sélection "
//                ],
//            ],
            "image" => array_map(function ($image) use ($shopify_product) {
                return [
                    $image->id => "https://url.demo.fr/img/p/1/4/2/7/8/0/142780.jpg",
                ];
            }, $shopify_product->images),
            "decli" => 0,
            "carac" => [
                "feature" => array_map(function ($option) {
                    return [
                        "name" => [
                            "1" => $option['name']
                        ]
                    ];
                }, $shopify_product->options),
                "value" => [
                    [
                        "id_feature" => "20",
                        "value" => [
                            "1" => "178"
                        ],
                        "id" => 218370
                    ],
                    [
                        "id_feature" => "21",
                        "value" => [
                            "1" => "Beige / crème"
                        ],
                        "id" => 5334
                    ]
                ]
            ],
            "manufacturer" => [
                "id" => 10,
                "name" => "Marque",
                "description" => [
                    "1" => "<h2>La gamme de produits</h2>"
                ],
                "short_description" => [
                    "1" => "<p>Marque</p>"
                ],
                "link_rewrite" => "marque",
                "meta_title" => [
                    "1" => "marque"
                ],
                "meta_keywords" => [
                    "1" => "marque"
                ],
                "meta_description" => [
                    "1" => "marque"
                ],
                "active" => "1"
            ]
        ];
    }

    /**
     * @throws \Exception
     */
    #[Route('/ec_shopify/install')]
    public function installAction(Request $request): Response
    {

        if (!Dataobject::getByPath('/Diffusion/Shopify')) {
            $diffusion = new DataObject\Diffusion();
            $diffusion->setParentID(WebsiteSetting::getByName('folderDiffusion')->getData());
            $diffusion->setKey('Shopify');
            $diffusion->setName('Shopify');
            $diffusion->setPlateforme('Shopify');
            $diffusion->setPublished(true);
            $diffusion->save();
            $lstConfig = $diffusion->getConfig();

        } else {
            $diffusion = Dataobject::getByPath('/Diffusion/Shopify');
        }
        

        // //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_access_token')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_access_token');
            $config->setIdconfig('shopify_access_token');
            $config->setPublished(true);
            $config->setName('Access token');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_api_key')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_api_key');
            $config->setIdconfig('shopify_api_key');
            $config->setPublished(true);
            $config->setName('API key');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_api_secret')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_api_secret');
            $config->setIdconfig('shopify_api_secret');
            $config->setPublished(true);
            $config->setName('API secret');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_api_hostname')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_api_hostname');
            $config->setIdconfig('shopify_api_hostname');
            $config->setPublished(true);
            $config->setName('API hostname');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_api_scope')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_api_scope');
            $config->setIdconfig('shopify_api_scope');
            $config->setPublished(true);
            $config->setName('API scopes');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        //Nomenclature BL
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_api_version')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_api_version');
            $config->setIdconfig('shopify_api_version');
            $config->setPublished(true);
            $config->setName('API version');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_webhook_secret')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_webhook_secret');
            $config->setIdconfig('shopify_webhook_secret');
            $config->setPublished(true);
            $config->setName('Shopify webhook secret');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }
        
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/shopify_location_id')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId());
            $config->setKey('shopify_location_id');
            $config->setIdconfig('shopify_location_id');
            $config->setPublished(true);
            $config->setName('Shopify Location ID');
            $config->setTypeConfig('input');
            $config->setValeur('');
            $config->save();
        }

        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/action_after_product_delete')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId())
                ->setKey('action_after_product_delete')
                ->setIdconfig('action_after_product_delete')
                ->setName('Suppresion d\'article Shopify après une suppression d\'article sur le Pim, true suppresion, false passage en brouillon')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save();
        }
        if (!Dataobject::getByPath('/Diffusion/'.$diffusion->getKey().'/import_product')) {
            $config = new DataObject\Config();
            $config->setParentID($diffusion->getId())
                ->setKey('import_product')
                ->setIdconfig('import_product')
                ->setName('Action lors de l\'import de produit Shopify, true mise à jour base pim, false reset')
                ->setValeur(0)
                ->setPublished(true)
                ->setTypeConfig('checkbox')
                ->save();
        }

        // if (!Dataobject::getByPath('/Diffusion/Shopify/desc_lenght')) {
        //     $config = new DataObject\Config();
        //     $config->setParentID(WebsiteSetting::getByName('folderConfig')->getData());
        //     $config->setKey('desc_lenght');
        //     $config->setIdconfig('desc_lenght');
        //     $config->setPublished(true);
        //     $config->setName('Description à utiliser');
        //     $config->setValeur(1);
        //     $config->setTypeList('[{"key":"Longue", "value":"long"}, {"key":"Courte", "value":"short"}]');
        //     $config->setTypeConfig('select');
        //     $config->save();
        // }

        //Dossier categ diffusion Prestashop
        $parent = Folder::getByPath('/Category/CategoryDiffusion');
        if (!Folder::getByPath("/Category/CategoryDiffusion/Shopify")) {
            $folder = new Folder();
            $folder->setParentID($parent->getId());
            $folder->setKey('Shopify');
            $folder->save();
        } else {
            $folder = Folder::getByPath('/Category/CategoryDiffusion/Shopify');
        }

        if (!Dataobject::getByPath('/Action/updateProductShopify')) {
            $action = new DataObject\Action();
            $action->setParentID(WebsiteSetting::getByName('folderAction')->getData());
            $action->setKey('updateProductShopify');
            $action->setName('updateProductShopify');
            $action->setAction(array('\bundles\ecShopifyBundle\src\Controller\PimcoreHookController::hookUpdateProductAction'));
            $action->setDescription('Mise à jour des informations d\'un produit');
            $action->setPublished(true);
            $action->save();
        }
        if (!Dataobject::getByPath('/Action/updateStockShopify')) {
            $action = new DataObject\Action();
            $action->setParentID(WebsiteSetting::getByName('folderAction')->getData());
            $action->setKey('updateStockShopify');
            $action->setName('updateStockShopify');
            $action->setAction(array('\bundles\ecShopifyBundle\src\Controller\PimcoreHookController::hookUpdateStockShopify'));
            $action->setDescription('Mise à jour des stocks Shopify');
            $action->setPublished(true);
            $action->save();
        }

        if (!Dataobject::getByPath('/Action/createProductShopify')) {
            $action = new DataObject\Action();
            $action->setParentID(WebsiteSetting::getByName('folderAction')->getData());
            $action->setKey('createProductShopify');
            $action->setName('createProductShopify');
            $action->setAction(array('\bundles\ecShopifyBundle\src\Controller\PimcoreHookController::hookCreateProductAction'));
            $action->setDescription('Création d\'un produit');
            $action->setPublished(true);
            $action->save();
        }

        if (!Dataobject::getByPath('/Action/deleteProductShopify')) {
            $action = new DataObject\Action();
            $action->setParentID(WebsiteSetting::getByName('folderAction')->getData());
            $action->setKey('deleteProductShopify');
            $action->setName('deleteProductShopify');
            $action->setAction(array('\bundles\ecShopifyBundle\src\Controller\PimcoreHookController::hookDeleteProductAction'));
            $action->setDescription('Création d\'un produit');
            $action->setPublished(true);
            $action->save();
        }

        // CRON
        if (!Dataobject::getByPath('/Cron/ImportProductShopify_'.$diffusion->getId())) {
            $cron = new DataObject\Cron();
            $cron->setParentID(WebsiteSetting::getByName('folderCron')->getData());
            $cron->setKey('ImportProductShopify_'.$diffusion->getId());
            $cron->setPrefix('ImportProductShopify_'.$diffusion->getId());
            $cron->setCommentaire('Import Produit Shopify '.$diffusion->getKey(). '('.$diffusion->getId().')' );
            $cron->setListStages('ImportProductShopify');
            $cron->setToken(md5(time()));
            $cron->setPublished(true);
            $cron->setStages(array('\bundles\ecShopifyBundle\src\Controller\ImportController::cronImportShopify'));
            $cron->save();
        }

        if (!Dataobject::getByPath('/Cron/GetProductShopify_'.$diffusion->getId())) {
            $cron = new DataObject\Cron();
            $cron->setParentID(WebsiteSetting::getByName('folderCron')->getData());
            $cron->setKey('GetProductShopify_'.$diffusion->getId());
            $cron->setPrefix('GetProductShopify_'.$diffusion->getId());
            $cron->setCommentaire('Get Produit Shopify '.$diffusion->getKey(). '('.$diffusion->getId().')' );
            $cron->setListStages('GetProductShopify');
            $cron->setToken(md5(time()));
            $cron->setPublished(true);
            // $cron->setVisibility('1');
            $cron->setStages(array('\bundles\ecShopifyBundle\src\Controller\MigrationController::cronGetFile'));
            $cron->save();
        }

        

        $diffusion->setId_folder($folder->getId());
        $diffusion->save();

        return new Response('<pre>ok</pre>');
    }

}

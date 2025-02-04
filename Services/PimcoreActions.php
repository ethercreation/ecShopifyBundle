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

namespace bundles\ecShopifyBundle\Services;

use bundles\ecMiddleBundle\Services\Outils;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Diffusion;
use Pimcore\Model\WebsiteSetting;
use Pimcore\Tool;
use Shopify\Rest\Admin2023_04\Product;

class PimcoreActions
{
    const PARENT_ID_CATEGORY = 1772;

    /**
     * @throws \Exception
     */
    static function createProductAction($json)
    {
        $diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $id_diffusion = $diffusion->getId();
        $api = new ShopifyApiClient();
        if (!is_object($diffusion)) {
            Outils::addLog('Diffusion non trouvée : '.json_encode($diffusion), 1, [], 'NOMDULOG');
            return false;
        }
        /** @var Product $data_product */
        $data_product = json_decode(json_encode($json), FALSE);
        Outils::addLog('Création d\'un produit:' . json_encode($data_product), 1, [], 'NOMDULOG');
        $prod = json_decode(json_encode([
            'id' => $json['id'],
//            'id_manufacturer' => null,
//            'id_supplier' => null,
            'id_category_default' => self::PARENT_ID_CATEGORY,
//            'id_shop_default' => null,
            'name' => $json['title'],
            'description' => $data_product->body_html,
            'description_short' => $data_product->body_html,
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
//            'tax_default' =>
            'id_tax' => Dataobject::getByPath('/Config/tax_default')->getValeur()

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

        $langPS = $json->lang ?? 1;

        // Verif si déjà crossid
        $idPim = Outils::getExist($prod->id, $id_diffusion, 'crossid', 'product');
        if ($idPim > 0) {
            return 'PS ' . $prod->id . ' - OK by ID ' . $idPim;
        }

        // Verif si EAN13-
        if (strlen($json['variants'][0]['barcode']) == 13) {
            $idPim = Outils::getExist($json['variants'][0]['barcode'], $id_diffusion, 'ean13', 'product');
            $diff = $diffusion;
            if ($idPim > 0) {
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
            }
            $tabCateg[] = $category;
        }
        $categList = array_unique($tabCateg);
        $caracList = 0;
        if ($carac) {
            $caracList = self::pullCarac($carac, $diffusion, $langPS);
        }

        if ($marque) {
            $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');
            if ($idMarq == 0) {
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

        if ($decli) {
            $decliList = self::pullDecli($decli, $diffusion, $langPS);
        } else {
            $decliList = 0;
        }

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

    /**
     * Mise à jour des informations d'un produit
     *
     * @param $declis
     * @param $diffusion
     * @param $langPS
     * @return array
     */
    public static function updateProduct($json)
    {
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
    }

    public static function pullDecli($declis, $diffusion, $langPS)
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

    public static function createMarque($marque, $diffusion, $langPS)
    {
        $id_parent = WebsiteSetting::getByName('folderMarque')->getData();
        $languages = Tool::getValidLanguages();

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

    public static function pullMarque($marque, $diffusion, $langPS)
    {
        $idMarq = Outils::getExist($marque->id, $diffusion->getID(), 'crossid', 'marque');
        if ($idMarq == 0) {
            $idMarq = Outils::putCreateMarque(marque: $marque, diffusion: $diffusion, langPS: $langPS);
        }
        return DataObject::getById(id: $idMarq);
    }

    public static function pullCarac($caracs, $diffusion, $langPS)
    {
        $caracList = array();
        foreach ($caracs->feature as $k => $json) {
            $idCarac = Outils::getExist($json->id, $diffusion->getID(), 'crossid', 'carac');

            if ($idCarac == 0) {
                $idCarac = Outils::putCreateCarac($json, $diffusion, $langPS);
            }

            $idPim = Outils::putCreateCaracValue($caracs->value[$k], $diffusion, $idCarac, $langPS);
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

        if ($parent > 0) {
            $id_parent = Outils::getExist($parent, $diffusion->getID(), 'crossid', 'category');
            if (!$id_parent) {
                $id_parent = $fopa;
            }
        } else {
            $id_parent = $fopa;
        }

        $languages = Tool::getValidLanguages();

        $newCateg = new DataObject\Category();
        $newCateg->setKey('Shopify_' . $categ->id_category);
        $newCateg->setPublished(true);
        $newCateg->setParentId($id_parent);
        $newCateg->setName($categ->name, 'fr');
        $newCateg->setName($categ->name, 'en');
        $newCateg->setName($categ->name, 'de');
        $newCateg->setDescription($categ->description);
        $newCateg->setLink_rewrite($categ->link_rewrite, 'fr');
        $newCateg->setLink_rewrite($categ->link_rewrite, 'en');
        $newCateg->setLink_rewrite($categ->link_rewrite, 'de');
        $newCateg->nohook = true;
        $newCateg->save();

        Outils::addCrossid($newCateg, $diffusion, $categ->id, false);
        return $newCateg;
    }

}
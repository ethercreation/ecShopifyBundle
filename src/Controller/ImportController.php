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


use bundles\ecMiddleBundle\Services\Outils;
use bundles\ecShopifyBundle\Controller\MigrationController;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Category;
use Pimcore\Model\DataObject\Diffusion;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\WebsiteSetting;
use Pimcore\Tool;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

class ImportController extends FrontendController
{
    const PARENT_ID_CATEGORY = 1772;
    /**
     * @var mixed|string[]
     */
    private mixed $languages;
    private mixed $list_id_diffusion;
    private mixed $folder;
    private mixed $diffusion;
    private mixed $categParent;

    public function __construct()
    {
        $this->languages = Tool::getValidLanguages();
        $this->folder['folderDeclinaison'] = WebsiteSetting::getByName('folderDeclinaison')->getData();
        $this->folder['folderAttribut'] = WebsiteSetting::getByName('folderAttribut')->getData();;
        $this->folder['folderProduct'] = WebsiteSetting::getByName('folderProduct')->getData();
        $this->folder['folderMarque'] = WebsiteSetting::getByName('folderMarque')->getData();
        $this->folder['folderCarac'] = WebsiteSetting::getByName('folderCarac')->getData();
        $this->folder['folderImages'] = WebsiteSetting::getByName('folderImages')->getData();
        
        $this->diffusion = Diffusion::getByPath('/Diffusion/Shopify');
        $this->categParent = DataObject::getByPath('/Category/CategoryDiffusion/Shopify')->getId();
    }
    

    /**
     * @throws MissingArgumentException
     * @throws \Exception
     */
    #[Route('/ec_shopify/ImportShopify')]
    public function cronImportShopify($params)
    {
        $cron = $params['cron'];
        $nbCron = $params['nbCron'];
        $stopTime = $params['stopTime'];

        if (!$stop_hook = Outils::getCache('stop_hook')) {
            $stop_hook = DataObject::getByPath('/Config/stop_hook');
            Outils::putCache('stop_hook', $stop_hook);
        }
        if ($stop_hook->getValeur() != 1) {
            $stop_hook->nohook = true;
            \Pimcore\Model\Version::disable();
            $stop_hook->setValeur(1);
            $stop_hook->save();
            \Pimcore\Model\Version::enable();
            $stop_hook->nohook = false;    
        }

        $traitements = Outils::query('SELECT *
                        FROM eci_midle_file_productShopify                    
                        WHERE (`log` = "NOK" or `log` IS NULL)
                        ');
        $jobLine = 0;
        foreach ($traitements as $traitem) {
            if ($stopTime < time() /* && ($jobLine > $nbCron) */) {
                return Outils::getValue('SELECT count(*) FROM eci_midle_file_productShopify WHERE (`log` = "NOK" or `log` IS NULL)');
            }
            $jobLine++;
            // if ($jobLine <= $nbCron) {
            //     continue;
            // }
            $result = $this->createProductAction($traitem);      
            Outils::addLog('Produit ' . $this->nettoyeId($traitem['id']) . ' : ' . json_encode($result));
            Outils::query('DELETE FROM eci_midle_file_productShopify WHERE i = ' . $traitem['i']);
        }
        
        $stop_hook->nohook = true;
        \Pimcore\Model\Version::disable();
        $stop_hook->setValeur(0);
        $stop_hook->save();
        \Pimcore\Model\Version::enable();
        $stop_hook->nohook = false;      
        return true;
    }

    /**
     * @throws MissingArgumentException
     * @throws \Exception
     */
    #[Route('/ec_shopify/importOne')]
    public function indexAction(Request $request): Response
    {
        // if (!Dataobject::getByPath('/Cron/ImportProductShopify_'.$this->diffusion->getId())) {
        //     $cron = new DataObject\Cron();
        //     $cron->setParentID(WebsiteSetting::getByName('folderCron')->getData());
        //     $cron->setKey('ImportProductShopify_'.$this->diffusion->getId());
        //     $cron->setPrefix('ImportProductShopify_'.$this->diffusion->getId());
        //     $cron->setCommentaire('Import Produit Shopify '.$this->diffusion->getKey(). '('.$this->diffusion->getId().')' );
        //     $cron->setListStages('ImportProductShopify');
        //     $cron->setToken(md5(time()));
        //     $cron->setPublished(true);
        //     $cron->setStages(array('\bundles\ecShopifyBundle\Controller\ImportController::cronImportShopify'));
        //     $cron->save();
        // }

        $id = $request->get('id');
        $result['id'] = 'Manque id';
        MigrationController::getProductByID('gid://shopify/Product/' . $id, 'produitUnitaireShopify');
        $row = Outils::getRow('SELECT * FROM eci_midle_file_produitUnitaireShopify WHERE id = "gid://shopify/Product/'.$id.'"');
          
        if ($row && is_array($row)) {
            $result = $this->createProductAction($row);
        }

        return new Response(json_encode($result));
    }

    public function nettoyeId($id) {
        $tab = explode('/', $id);
        return $tab[count($tab) - 1];
    }

    public function updateObjectPrice($id) {
        $lstPrix =  Outils::query('SELECT o.id as id
        FROM object_' . Outils::getIDClass('priceSelling').' o                     
        WHERE (`parentId` = '.(int)$id.' OR decli__id = '.(int)$id.')
        AND (o.archive IS NULL OR o.archive = 0)');
        foreach ($lstPrix as $prix) {
            $obj = DataObject::getById($prix['id']);
            $obj->forcequeue = true;
            $obj->save();
        }
    }
    /**
     * @throws \Exception
     */
    public function createProductAction($datas)
    {
        $diffusion = $this->diffusion;
        $id_diffusion = $diffusion->getId();
        
        if (!is_object($diffusion)) {
            return false;
        }

        $categ = [];
        if (array_key_exists('edges', json_decode($datas['collections'], true))) {
            $categ = array_map(function ($collection) {
                $coll = $collection['node'];
                return [
                    'id' => $this->nettoyeId($coll['id']),
                    'id_category' => $this->nettoyeId($coll['id']),
                    'id_category_default' => $this->nettoyeId($coll['id']),
                    'name' => $this->nettoyeId($coll['title']),
                    'active' => 1,
                    'description' => $coll['descriptionHtml'],
                    'id_parent' => $this->categParent,
                    // 'link_rewrite' => $collection->handle,
                    //                'meta_title' => null,
                    'meta_keywords' => null,
                    //                'meta_description' => null,
                ];
            }, json_decode($datas['collections'], true)['edges']);
        } 
   
        $declis = json_decode($datas['variants'], true)['edges']; 
        
        $simple = 0;
        // $simple = 1;
        // if (count($declis) > 1) {
        //     $simple = 0;
        // }
        $firstDec = $declis[0]['node'];
        
        $prod = [
            'id' => $this->nettoyeId($datas['id']),
            'id_category_default' => $categ[0]['id']??'',
            'name' => $datas['title'],
            'description' => $datas['descriptionHtml'],
            'description_short' => '',
            'quantity' => $firstDec['inventoryQuantity']??0,
            'price' => $firstDec['price']??0,
            // 'wholesale_price' => '',
            // 'ecotax' => '',
            'reference' => $firstDec['sku']??'',
            'supplier_reference' => $firstDec['sku']??'',
//            'width' => null,
//            'height' => $prod->variants[0]->height,
//            'depth' => null,
            // 'weight' => $data_product->variants[0]->weight,
            'ean13' => strlen($firstDec['barcode']) === 13 ? $firstDec['barcode'] : '0000000000000',
            'link_rewrite' => $datas['handle'],
            'active' => strtolower($datas['status']) == 'active' ? 1 : 0,
        ];
        
        $image = null;
        if (array_key_exists('edges', json_decode($datas['images'], true))) {
            foreach (json_decode($datas['images'], true)['edges'] as $im) {
                $image[$this->nettoyeId($im['node']['id'])] = $im['node']['originalSrc'];
            }
        }

        $decli = 0;
        if (!$simple) {
            $attribute = [];
            $values = [];
            $info = [];
            $options = [];
            $i = 0;
            foreach (json_decode($datas['variants'], true)['edges'] as $variants) {
                $variant = $variants['node'];
                $i++;
                foreach ($variant['selectedOptions'] as $option) {
                    $attribute[$this->nettoyeId($variant['id'])][] = [
                        'id' => md5($option['name']),
                        'name' => ['1' => $option['name']],
                        'position' => $i,
                        'active' => 1
                    ];

                    $values[$this->nettoyeId($variant['id'])][] = [
                        'id' => md5($option['name'].':'.$option['value']),
                        'name' => ['1' => $option['value']],
                        'position' => $i,
                        'active' => 1
                    ];
                }

                $info[$this->nettoyeId($variant['id'])] = [
                    'reference_declinaison' => $variant['sku'],
                    'reference' => $variant['sku'],
                    'quantity' => $variant['inventoryQuantity'],
                    'ean13' => strlen($variant['barcode']) === 13 ? $variant['barcode'] : '0000000000000',
                    'weight' => 0,
                    // 'wholesale_price' => $variant->inventory_item->cost,
                    'price' => $variant['price'],
                    'id_product' => $this->nettoyeId($datas['id']),
                    'id' => $this->nettoyeId($variant['id']),
                    'supplier_reference' => $variant['sku'],
                    'location' => '',
                    'isbn' => '',
                    'upc' => '',
                    'mpn' => '',
                    // "ecotax" => "0.000000",
                    'default_on' => ($i == 1) ? 1 : 0,
                    'active' => 1
                ];
            }

            $decli = json_decode(json_encode([
                'attribute' => $attribute,
                'value' => $values,
                'info' => $info
            ]));
        }
        
        $feature = [];
        $value = [];
        if (array_key_exists('edges', json_decode($datas['metafields'], true))) {
            foreach (json_decode($datas['metafields'], true)['edges'] as $metafield) {
                if (is_array($metafield) && array_key_exists('node', $metafield)
                    && is_array($metafield['node']) && array_key_exists('definition', $metafield['node'])
                    && is_array($metafield['node']['definition']) && array_key_exists('name', $metafield['node']['definition'])) {
                    $name = $metafield['node']['definition']['name'];
                    $values = json_decode($metafield['node']['value'], true);
                    $idF = $this->nettoyeId($metafield['node']['id']);
                    $feature[] = ['name' => [1 => $name], 'id' => $idF];
                    if (is_array($values)) {
                        foreach ($values as $gid) {
                            $metaobjects = json_decode($datas['metaObject'], true);
                            if (isset($metaobjects[$gid])) {
                                $displayName = $metaobjects[$gid]['displayName'];
                                $value[] = ['custom' => 0, 'value' => [1 => $displayName], 'id_feature' => $idF, 'id' => $this->nettoyeId($metaobjects[$gid]['id'])];
                            }
                        }
                    }
                } elseif (is_array($metafield) && array_key_exists('node', $metafield)
                    && is_array($metafield['node'])) {
                    $name = $metafield['node']['key'];
                    $values = json_decode($metafield['node']['value'], true);
                    $idF = $this->nettoyeId($metafield['node']['id']);
                    $feature[] = ['name' => [1 => $name], 'id' => $idF];
                    $value[] = ['custom' => 0, 'value' => [1 => $name], 'id_feature' => $idF, 'id' => $idF];
                }
            }
        }

        $langPS = ['fr' => 1];
        $marque = 0;
        $carac = ['feature' => $feature, 'value' => $value];

        $prod = json_decode(json_encode($prod));
        $categ = json_decode(json_encode($categ));
        // $image = json_decode(json_encode($image));
        $decli = json_decode(json_encode($decli));
        $carac = json_decode(json_encode($carac));

        if (!$import_product = Outils::getCache('import_product')) {
            $import_product = DataObject::getByPath('/Config/import_product');
            Outils::putCache('import_product', $import_product);
        }

        // Verif si déjà crossid
        $idPim = Outils::getExist($prod->id, $id_diffusion, 'crossid', 'product');
        if ($idPim > 0) {
            Outils::addLog('Shopify ' . $prod->id . ' - OK by ID ' . $idPim);
            return 'Shopify ' . $prod->id . ' - OK by ID ' . $idPim;
        }

        // Verif si EAN13-
        if (strlen($prod->ean13) == 13 && $prod->ean13 != '0000000000000') {
            $idPim = Outils::getExist($prod->ean13, "", 'ean13', 'product');
            if ($idPim && $idPim != '') {
                $idPims = json_decode($idPim, true);
                if (is_array($idPims) && array_key_exists(0, $idPims)) {
                    Outils::addLog('Shopify ' . $prod->id . ' - OK by EAN13 ' . $prod->ean13 . ' - IDPIM ' . $idPim);
                    Outils::addCrossid($idPims[0]['id'], $id_diffusion, $prod->id, false);
                    $objpim = DataObject::getById($idPims[0]['id']);
                    $objpim->forcequeue = true;
                    $objpim->save();
                    $this->updateObjectPrice($idPims[0]['id']);
                    return 'Shopify ' . $prod->id . ' - OK by EAN13 ' . $prod->ean13;
                }
            }
        }

        // Verif si SKU
        if ($prod->reference) {
            $idPimDecli = Outils::getExist($prod->reference, '', 'crossid', 'declinaison');
            if ($idPimDecli && $idPimDecli != '') {
                $infoDecli = json_decode($idPimDecli, true);
                if (is_array($infoDecli) && array_key_exists(0, $infoDecli)) {
                    $idPim = DataObject::getById($infoDecli[0]['id'])->getParentID();
                    $diff = $diffusion;
                    if ($idPim > 0) {
                        Outils::addCrossid($infoDecli[0]['id'], $id_diffusion, $prod->id, false);
                        Outils::addCrossid($idPim, $id_diffusion, $prod->id, false);
                        $objpim = DataObject::getById($idPim);
                        $objpim->forcequeue = true;
                        $objpim->save();
                        $this->updateObjectPrice($infoDecli[0]['id']);
                        Outils::addLog('Shopify ' . $prod->id . ' - OK by SKU DECLI :  ' . $prod->reference . ' - IDPIM ' . $idPim . '  - ID DECLI ' . $infoDecli[0]['id']);
                        return 'Shopify ' . $prod->id . ' - OK by SKU DECLI :  ' . $prod->reference . ' - IDPIM ' . $idPim . '  - ID DECLI ' . $infoDecli[0]['id'];
                    }
                }
            }
        }

        // // Verif si SKU
        // if ($prod->reference) {
        //     $idPim = Outils::getExist($prod->reference, $id_diffusion, 'reference', 'product');
        //     $diff = $diffusion;
        //     if ($idPim > 0) {
        //         return 'Shopify ' . $prod->id . ' - OK by sku ' . $idPim;
        //     }
        // }

        $tabCateg = array();
        foreach ($categ as $json_category) {
            $idPim = Outils::getExist(
                search: $json_category->id_category,
                source: $diffusion->getID(),
                objet: 'category'
            );

            if ($idPim > 0) {
                $category = Category::getById($idPim);
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
            $caracList = $this->pullCarac($carac, $diffusion, $langPS);
        }

        if ($image) {
            $imageList = Outils::putImage($image);
        } else {
            $imageList = 0;
        }

        $decliList = 0;
        if ($decli) {
            $decliList = $this->pullDecli($decli, $diffusion, $langPS);
        }
        
        return Outils::putCreateProduct(
            prod: $prod,
            diffusion: $diffusion,
            categList: $categList,
            caracList: $caracList,
            marqueList: 0,
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
            $idPim = 0;
            if ($idCarac == 0) {
//                $idCarac = $this->createCarac($json, $diffusion, $langPS);
                $idCarac = Outils::putCreateCarac($json, $diffusion, $langPS);
            }

//            $idPim = Outils::getExist($caracs->value[$k]->id, $diffusion->getID(), 'crossid', 'caracValue');

//            if ($idPim == 0) {
//            $idPim = $this->createCaracValue($caracs->value[$k], $diffusion, $idCarac, $langPS);
            if (is_array($caracs->value) && array_key_exists($k, $caracs->value)) {
                $idPim = Outils::putCreateCaracValue($caracs->value[$k], $diffusion, $idCarac, $langPS);
            }
//            }
            if ($idPim) {
                $caracList[] = DataObject::getById($idPim);
            }
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

}

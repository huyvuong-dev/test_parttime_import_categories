<?php

namespace Magenest\ImportCategories\Model\Import;

use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\ImportExport\Helper\Data as ImportHelper;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper;
use Magento\ImportExport\Model\ResourceModel\Import\Data;

$GLOBALS["arrCategoryName"]  = array();
$GLOBALS["arrCategoryUrlKey"]  = array();
$GLOBALS["arrCategoryId"]  = array();
/**
 * Class Courses
 */
class CustomImport extends AbstractEntity
{

    const ENTITY_CODE = 'categories';
    const TABLE = 'categories';
    const ENTITY_ID_COLUMN = 'entity_id';
    const PRODUCTS_ONLY = 'PRODUCTS';
    const STATIC_BLOCK_ONLY = 'PAGE';
    const STATIC_BLOCK_ONLY_AND_PRODUCTS = 'PRODUCTS_AND_PAGE';
    const APPEND = 'append';
    const REPLACE = 'replace';
    const DELETE = 'delete';
    /**
     * If we should check column names
     */
    protected $needColumnCheck = true;

    /**
     * Need to log in import history
     */
    protected $logInHistory = true;

    /**
     * Permanent entity columns.
     */
    protected $_permanentAttributes = [
        'entity_id'
    ];

    /**
     * Valid column names
     */
    protected $validColumnNames = [
        'entity_id',
        'category_name',
        'is_active',
        'url_key',
        'description',
        'page_title',
        'meta_keywords',
        'meta_description',
        'include_in_navigation_menu',
        'display_mode',
        'is_anchor',
        'available_sort_by',
        'default_product_listing',
        'price_step',
        'use_parent_category',
        'apply_to_products',
        'custom_design',
        'active_from',
        'active_to'
    ];

    /**
     * @var AdapterInterface
     */
    protected $connection;

    /**
     * @var ResourceConnection
     */
    private $resource;
    private $_categoryFactory;
    private $_category;
    private $_collectionFactory;
    private $_storeManager;
    /**
     * Courses constructor.
     *
     * @param JsonHelper $jsonHelper
     * @param ImportHelper $importExportData
     * @param Data $importData
     * @param ResourceConnection $resource
     * @param Helper $resourceHelper
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     */
    public function __construct(
        JsonHelper $jsonHelper,
        ImportHelper $importExportData,
        Data $importData,
        ResourceConnection $resource,
        Helper $resourceHelper,
        ProcessingErrorAggregatorInterface $errorAggregator,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\Category $category,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $collectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->_storeManager = $storeManager;
        $this->_collectionFactory = $collectionFactory;
        $this->_category = $category;
        $this->_categoryFactory = $categoryFactory;
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->resource = $resource;
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;
        $this->initMessageTemplates();
    }

    /**
     * Entity type code getter.
     *
     * @return string
     */
    public function getEntityTypeCode()
    {
        return static::ENTITY_CODE;
    }

    /**
     * Get available columns
     *
     * @return array
     */
    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    public function getNextAutoincrement($tableName)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // Instance of object manager
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $entityStatus = $connection->showTableStatus($tableName);

        if (empty($entityStatus['Auto_increment'])) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Cannot get autoincrement value'));
        }
        return $entityStatus['Auto_increment'];

    }

    public function myValidateData($date)
    {
        $format = 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $date);
        // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
        return $d && $d->format($format) === $date;
    }
    function array_is_unique($array) {
        return array_unique($array) == $array;
    }
     /**
     * Row validation
     *
     * @param array $rowData
     * @param int $rowNum
     *
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        $param = $this->getParameters();
        if ($param['behavior'] == self::APPEND){
            $category_name = $rowData['category_name'] ?? '';
            $is_active = (int)$rowData['is_active'] ?? 0;
            $url_key = $rowData['url_key'] ?? '';
            $include_in_navigation_menu = (int)$rowData['include_in_navigation_menu'] ?? 0;
            $display_mode = (int)$rowData['display_mode'] ?? 0;
            $is_anchor = (int)$rowData['is_anchor'] ?? 0;
            $price_step = (int)$rowData['price_step'] ?? 0;
            $use_parent_category = (int)$rowData['use_parent_category'] ?? 0;
            $active_from = $rowData['active_from'] ?? '';
            $active_to = $rowData['active_to'] ?? '';

            $tableName = 'catalog_category_entity';
            $nextIdAutoincrement = $this->getNextAutoincrement($tableName);
            $col = $this->getCollection();
            //Condition validate
            foreach ($col as $data){
                if (!in_array($data->getName(),$GLOBALS['arrCategoryName'])){
                    array_push($GLOBALS['arrCategoryName'],strtolower($data->getName()));
                }
                if(!in_array($data->getUrlKey(),$GLOBALS['arrCategoryUrlKey'])){
                    array_push($GLOBALS['arrCategoryUrlKey'],strtolower($data->getUrlKey()));
                }
                if (!in_array($data->getId(),$GLOBALS['arrCategoryId'])){
                    array_push($GLOBALS['arrCategoryId'],$data->getId());
                }

            }

            //Validate: coincidental name
            $name = strtolower($category_name);
            if (!$category_name || in_array($name,$GLOBALS['arrCategoryName'])) {
                $this->addRowError('NameIsRequired', $rowNum);
            }

            //Validate: add new name to check coincidental name
            if (!in_array($name,$GLOBALS['arrCategoryName'])){
                array_push($GLOBALS['arrCategoryName'],$name);
            }

            if ($is_active < 0 || $is_active > 1) {
                $this->addRowError('ActiveIsRequired', $rowNum);
            }

            //Validate: coincidental url key
            $url = strtolower($url_key);
            if (!$url_key || in_array($url,$GLOBALS['arrCategoryUrlKey'])) {
                $this->addRowError('UrlKeyIsRequired', $rowNum);
            }

            if (!in_array($url,$GLOBALS['arrCategoryUrlKey'])){
                array_push($GLOBALS['arrCategoryUrlKey'],$url);
            }

            if ($include_in_navigation_menu < 0 || $include_in_navigation_menu > 1) {
                $this->addRowError('IncludeInNavigationMenuIsRequired', $rowNum);
            }
            if (!$display_mode){
                $display_mode = 1;
            }
            if ($display_mode < 1 || $display_mode > 3) {
                $this->addRowError('DisplayModeIsRequired', $rowNum);
            }

            if ($is_anchor < 0 || $is_anchor > 1) {
                $this->addRowError('IsAnchorIsRequired', $rowNum);
            }

            if ($price_step < 0) {
                $this->addRowError('PriceStepIsRequired', $rowNum);
            }

            //Validate: Parent_Id
            if ($use_parent_category < 0 || ($use_parent_category > 0 && !in_array($use_parent_category,$GLOBALS['arrCategoryId']))) {
                $this->addRowError('UseParentCategoryIsRequired', $rowNum);
            }else{
                if (!in_array($nextIdAutoincrement,$GLOBALS['arrCategoryId'])){
                    array_push($GLOBALS['arrCategoryId'],$nextIdAutoincrement);
                }else{
                    $nextIdParent = (string)(end($GLOBALS['arrCategoryId'])+1) ;
                    array_push($GLOBALS['arrCategoryId'],$nextIdParent);
                }
            }

            if ($active_from != "" && $this->myValidateData($active_from) != true) {
                // it's not a date
                $this->addRowError('ActiveFromIsRequired', $rowNum);
            }

            if ($active_to != "" && $this->myValidateData($active_to) != true) {
                // it's not a date
                $this->addRowError('ActiveToIsRequired', $rowNum);
            }


        }elseif ($param['behavior'] == self::DELETE){
            $category_id = (int)$rowData['entity_id'] ?? 0;
            array_push($GLOBALS['arrCategoryId'],$category_id);
            $uniqueId = $this->array_is_unique($GLOBALS['arrCategoryId']);
            $ids = $this->getCollection()->getAllIds();
            if (!$category_id || !in_array($category_id,$ids) || !$uniqueId){
                $this->addRowError('IdIsRequired', $rowNum);
            }
        }else{
            $collection = $this->getCollection();
            foreach ($collection as $data){
                $value = $data->getData();
                array_push($GLOBALS['arrCategoryName'],$value['name']);
                if (isset($value['url_key']))
                    array_push($GLOBALS['arrCategoryUrlKey'],$value['url_key']);
            }
            $category_id = (int)$rowData['entity_id'] ?? 0;
            $category_name = $rowData['category_name'] ?? '';
            $url_key = $rowData['url_key'] ?? '';
            array_push($GLOBALS['arrCategoryId'],$category_id);
            if (isset($category_name))
                array_push($GLOBALS['arrCategoryName'],$category_name);
            if (isset($url_key))
                array_push($GLOBALS['arrCategoryUrlKey'],$url_key);
            $uniqueId = $this->array_is_unique($GLOBALS['arrCategoryId']);
            $uniqueName = $this->array_is_unique($GLOBALS['arrCategoryName']);
            $uniqueUrlKey = $this->array_is_unique($GLOBALS['arrCategoryUrlKey']);
            $ids = $collection->getAllIds();
            if (!$category_id || !in_array($category_id,$ids)){
                $this->addRowError('IdNotExistIsRequired', $rowNum);
            }
            if (!$uniqueId)
                $this->addRowError('uniqueIdIsRequired', $rowNum);
            if (!$uniqueName)
                $this->addRowError('uniqueNameIsRequired', $rowNum);
            if (!$uniqueUrlKey)
                $this->addRowError('uniqueUrlKeyIsRequired', $rowNum);
        }


        if (isset($this->_validatedRows[$rowNum])) {
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }

        $this->_validatedRows[$rowNum] = true;

        return !$this->getErrorAggregator()->isRowInvalid($rowNum);


    }


    /**
     * Import data
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function _importData(): bool
    {
        switch ($this->getBehavior()) {
            case Import::BEHAVIOR_DELETE:
                $this->deleteEntity();
                break;
            case Import::BEHAVIOR_REPLACE:
                $this->saveAndReplaceEntity();
                break;
            case Import::BEHAVIOR_APPEND:
                $this->saveAndReplaceEntity();
                break;
        }

        return true;
    }

    /**
     * Delete entities
     *
     * @return bool
     */
    private function deleteEntity(): bool
    {
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                $this->validateRow($rowData, $rowNum);

                if (!$this->getErrorAggregator()->isRowInvalid($rowNum)) {
                    $rowId = $rowData[static::ENTITY_ID_COLUMN];
                    $rows[] = $rowId;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                }
            }
        }

        if ($rows) {
            return $this->deleteEntityFinish(array_unique($rows));
        }

        return false;
    }

    /**
     * Save and replace entities
     *
     * @return void
     */
    private function saveAndReplaceEntity()
    {
        $behavior = $this->getBehavior();
        $rows = [];
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $entityList = [];

            foreach ($bunch as $rowNum => $row) {
                if (!$this->validateRow($row, $rowNum)) {
                    continue;
                }

                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);

                    continue;
                }

                $rowId = $row[static::ENTITY_ID_COLUMN];
                $rows[] = $rowId;
                $columnValues = [];

                foreach ($this->getAvailableColumns() as $columnKey) {
                    if (isset($row[$columnKey]))
                        $columnValues[$columnKey] = $row[$columnKey];
                }

                $entityList[$rowId][] = $columnValues;
                $this->countItemsCreated += (int)!isset($row[static::ENTITY_ID_COLUMN]);
                $this->countItemsUpdated += (int)isset($row[static::ENTITY_ID_COLUMN]);
            }

            if (Import::BEHAVIOR_REPLACE === $behavior) {
                if ($rows && $this->deleteEntityFinish(array_unique($rows))) {
                    $this->saveEntityFinish($entityList);
                }
            } elseif (Import::BEHAVIOR_APPEND === $behavior) {
                $this->saveEntityFinish($entityList);
            }
        }
    }

    private function getCollection(){
        $collections = $this->_collectionFactory->create()->addAttributeToSelect('*')->setStore($this->_storeManager->getStore());
        return $collections;
    }
    /**
     * Save entities
     *
     * @param array $entityData
     *
     * @return bool
     */
    private function saveEntityFinish(array $entityData): bool
    {
        if ($entityData) {
            foreach ($entityData as $entityRows) {
                foreach ($entityRows as $row) {
                    $collections = $this->_collectionFactory->create()->addAttributeToSelect('*')->setStore($this->_storeManager->getStore());
                    $category = $this->_categoryFactory->create();
                    if (isset($row['category_name']))
                        $category->setName($row['category_name']);
                    if (isset($row['is_active'])){
                        if ($row['is_active'] == 1) {
                            $category->setIsActive(true);
                        } else $category->setIsActive(false);
                    }
                    if (isset($row['is_anchor'])){
                        if ($row['is_anchor'] == 1){
                            $category->setData('is_anchor',true);
                        }else $category->setData('is_anchor',false);
                    }
                    if (isset($row['include_in_navigation_menu'])){
                        if ($row['include_in_navigation_menu'] == 1){
                            $category->setData('include_in_menu',true);
                        }else $category->setData('include_in_menu',false);
                    }
                    if (isset($row['url_key']))
                        $category->setUrlKey($row['url_key']);
                    if (isset($row['description']))
                        $category->setData('description', $row['description']);
                    if (isset($row['page_title']))
                        $category->setData('meta_title', $row['page_title']);
                    if (isset($row['meta_keywords']))
                        $category->setData('meta_keywords', $row['meta_keywords']);
                    if (isset($row['meta_description']))
                        $category->setData('meta_description', $row['meta_description']);
                    if (isset($row['display_mode'])){
                        if ($row['display_mode'] == 0 || $row['display_mode'] == 1) {
                            $category->setData('display_mode',self::PRODUCTS_ONLY);
                        }elseif ($row['display_mode'] == 2){
                            $category->setData('display_mode',self::STATIC_BLOCK_ONLY);
                        }else{
                            $category->setData('display_mode',self::STATIC_BLOCK_ONLY_AND_PRODUCTS);
                        }
                    }
                    if (isset($row['price_step'])){
                        if ($row['price_step'] > 0){
                            $category->setData('filter_price_range',$row['price_step']);
                        }
                    }
                    if (isset($row['active_from']))
                        $category->setCreatedAt($row['active_from']);
                    if (isset($row['active_to']))
                        $category->setUpdatedAt($row['active_to']);

                    if (isset($row['use_parent_category']))
                    {
                        $parentId = $row['use_parent_category'];
                        if ($row['use_parent_category'] < 2){
                            $category->setParentId(2);
                            $parentId = 2;
                        }else{
                            $category->setParentId($row['use_parent_category']);
                            $parentId = $row['use_parent_category'];
                        }
                        $category->setPath($collections->getItemById($parentId)->getPath());
                    }else{
                        $category->setPath($collections->getItemById(2)->getPath());
                    }

                    $category->save();
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Delete entities
     *
     * @param array $entityIds
     *
     * @return bool
     */
    private function deleteEntityFinish(array $entityIds): bool
    {
        if ($entityIds) {
            try {
                $collections = $this->_collectionFactory->create()->addAttributeToSelect('*')->setStore($this->_storeManager->getStore());
                foreach ($collections as $collection){
                    if (in_array($collection->getId(),$entityIds)){
                        $collection->delete();
                    }
                }
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     * Get available columns
     *
     * @return array
     */
    private function getAvailableColumns(): array
    {
        return $this->validColumnNames;
    }

    /**
     * Init Error Messages
     */
    private function initMessageTemplates()
    {
        $this->addMessageTemplate(
            'NameIsRequired',
            __('The name cannot be empty.')
        );
        $this->addMessageTemplate(
            'DurationIsRequired',
            __('Duration should be greater than 0.')
        );
    }

}

?>
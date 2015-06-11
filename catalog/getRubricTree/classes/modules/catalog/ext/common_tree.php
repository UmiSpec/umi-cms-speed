<?php
abstract class common_tree extends catalog {
    /**
     * Получить все категории на сайте, вложенные в $categoryId на глубину $childs.
     * Если указан $currentPageId, то у соответствующей категории ставится атрибут "активная" (@status = 'active').
     * Если указан $showCount, то в вывод добавляется количество товаров в разделе.
     *
     * @param $categoryId
     * @param int $childs
     * @param bool $currentPageId
     * @param bool $showCount
     * @return array
     */
    public function getRubricTree($categoryId, $childs = 1, $currentPageId = false, $showCount = false) {
        if((string) $categoryId != '0') $categoryId = $this->analyzeRequiredPath($categoryId);

        if (!$childs) $childs = getRequest('param2');
        $childs = intval($childs);
        if ($childs <= 0) $childs = 1;
        $showCount = ($showCount === '1');

        $sel = new selector('pages');
        $sel->types('hierarchy-type')->name('catalog', 'category');
        $sel->where('hierarchy')->page($categoryId)->childs($childs);

        if(!$sel->length()) {
            return '';
        }

        $arTree = array();
        $arRoots = array();
        $arCategories = array();

        $hierarchy = umiHierarchy::getInstance();

        foreach($sel as $category) {
            if(!$category instanceof umiHierarchyElement)
                continue;

            $parentId = $category->getParentId();

            $id = $category->getId();

            if($parentId == $categoryId) {
                $arRoots[] = $id;
            }

            if(!isset($arTree[$parentId])) {
                $arTree[$parentId] = array();
            }

            $arTree[$parentId][] = $id;

            $arCategory = $this->prepareCategory($category, $hierarchy, $currentPageId, $showCount);

            $arCategories[$id] = $arCategory;

            $hierarchy->unloadElement($id);
        }

        $return = array();

        foreach($arRoots as $root_id) {
            $return[] = $this->renderCategory($root_id, $arTree, $arCategories);
        }

        return array(
            'items' => array(
                'attribute:id' => $categoryId,
                'nodes:item' => $return
            )
        );
    }

    /**
     * Подготавливает категорию для вывода
     *
     * @param $category
     * @param $hierarchy
     * @param bool $currentPageId
     * @return array
     */
    public function prepareCategory($category, $hierarchy, $currentPageId = false, $showCount = false) {
        $id = $category->getId();

        $arCategory = array(
            'attribute:id' => $id,
            'attribute:name' => $category->getName(),
            'attribute:link' => $hierarchy->getPathById($id),
        );

        if($currentPageId == $id) {
            $arCategory['attribute:status'] = 'active';
        }

        if($showCount) {
            $hierarchyTypeID = $this->getObjectHierarchyTypeId();

            $arCategory['attribute:count'] = $hierarchy->getChildsCount($id, true, true, 10, $hierarchyTypeID);
        }

        return $arCategory;
    }

    /**
     * Выводит категорию для xslt
     *
     * @param $id
     * @param $arTree
     * @param $arCategories
     * @return mixed
     */
    public function renderCategory($id, $arTree, $arCategories) {
        $items = array();
        $line = isset($arCategories[$id]) ? $arCategories[$id] : array();

        if(isset($arTree[$id]) && count($arTree[$id]) > 0) {
            foreach($arTree[$id] as $childId) {
                $items[] = $this->renderCategory($childId, $arTree, $arCategories);
            }
        }

        if(count($items) > 0) {
            $line['items'] = array(
                'attribute:id' => $id,
                'nodes:item' => $items
            );
        }

        return def_module::parseTemplate('default', $line, $id);
    }

    static $object_hierarchy_type_id;

    protected function getObjectHierarchyTypeId() {
        if(is_null(self::$object_hierarchy_type_id)) {
            $type = umiHierarchyTypesCollection::getInstance()->getTypeByName('catalog', 'object');

            self::$object_hierarchy_type_id = $type ? $type->getId() : false;
        }

        return self::$object_hierarchy_type_id;
    }
};
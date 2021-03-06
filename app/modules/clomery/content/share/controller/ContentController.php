<?php


namespace clomery\content\controller;

use ArrayObject;
use clomery\content\parser\Content;
use clomery\main\table\ArticleTable;
use suda\application\database\Table;
use suda\database\exception\SQLException;
use suda\database\statement\PrepareTrait;
use support\openmethod\PageData;

class ContentController extends CategoryController
{
    public static $showFields = ['id', 'slug', 'title', 'stick', 'user', 'create_time', 'modify_time', 'category', 'description', 'image', 'views', 'status'];
    public static $viewFields = ['id', 'slug', 'title', 'stick', 'user', 'content', 'create_time', 'modify_time', 'category', 'description', 'image', 'views', 'status'];

    /**
     * @var TagController
     */
    protected $tagController;
    /**
     * @var CategoryController
     */
    protected $categoryController;

    public function __construct(Table $table, Table $category, Table $tag, Table $relate)
    {
        parent::__construct($table);
        $this->categoryController = new CategoryController($category);
        $this->tagController = new TagController($tag, $relate);
    }

    /**
     * @param array $data
     * @return array|null
     * @throws SQLException
     */
    public function save(array $data)
    {
        $data = $this->addIdIfUnique($data);
        if (array_key_exists('id', $data) === false) {
            $data['views'] = intval($data['views'] ?? 0);
            $data['stick'] = intval($data['stick'] ?? 0);
            $data['create_time'] = intval($data['create_time'] ?? time());
            $data['modify_time'] = intval($data['modify_time'] ?? time());
        }
        return parent::save($data);
    }

    /**
     * @param array $data
     * @return array
     * @throws SQLException
     */
    protected function addIdIfUnique(array $data)
    {
        if (array_key_exists('id', $data) === false && array_key_exists('slug', $data)) {
            $get = $this->table->read(['id', 'content_hash'])->where(['slug' => $data['slug']])->one();
            if ($get) {
                $data['id'] = $get['id'];
                $content = $data['content'];
                if ($content instanceof Content) {
                    $hash = md5($content->raw());
                } else {
                    $hash = md5($content);
                }
                $hash = strtolower($hash);
                if (strcmp($hash, strtolower($get['content_hash'])) != 0) {
                    $data['content_hash'] = $hash;
                    $data['modify_time'] = $data['modify_time'] ?? time();
                }
            }
        }
        return $data;
    }

    /**
     * @param string $id
     * @param int $size
     * @return bool
     * @throws SQLException
     */
    public function pushCountView(string $id, int $size)
    {
        return $this->table->write('`views` = `views` + :num')
            ->addValue('num', $size)
            ->where(['id' => $id])
            ->ok();
    }

    /**
     * 获取 上一篇 下一篇 文章
     *
     * @param string $article
     * @param array $fields
     * @return array
     * @throws SQLException
     */
    public function getNearArticle(string $article, array $fields = []): array
    {
        $create = $this->table->read('create_time')->where(['id' => $article])->field('create_time');
        return $this->getNearArticleByTime(intval($create), $fields);
    }

    /**
     * 获取文章
     * @param string $article
     * @param array|null $select
     * @return array|null
     * @throws SQLException
     */
    public function getArticle(string $article, array $select = null): ?array
    {
        $where = [];
        if (is_numeric($article)) {
            $where['id'] = $article;
        } else {
            $where['slug'] = $article;
        }
        return $this->table->read($select ?? static::$viewFields)->where($where)->one();
    }

    /**
     * @param string $categoryId
     * @return int
     * @throws SQLException
     */
    public function getCategoryCount(string $categoryId)
    {
        return $this->table->read('count(id) as count')->where(['category' => $categoryId])->field('count', 0);
    }

    /**
     * 根据时间获取相近文章
     *
     * @param integer $create
     * @param array $fields
     * @return array
     * @throws SQLException
     */
    public function getNearArticleByTime(int $create, array $fields = []): array
    {
        $previousCondition = ['create_time' => ['<', $create], 'status' => ArticleTable::PUBLISH];
        $nextCondition = ['create_time' => ['>', $create], 'status' => ArticleTable::PUBLISH];
        $previous = $this->table->read($fields ?: static::$showFields)->where($previousCondition)->orderBy('create_time', 'DESC')->one();
        $next = $this->table->read($fields ?: static::$showFields)->where($nextCondition)->orderBy('create_time')->one();
        return [$previous, $next];
    }

    use PrepareTrait;

    /**
     * 筛选文章
     * @param null|string $search
     * @param null|string $category
     * @param array|null $tags
     * @param int|null $page
     * @param int $row
     * @param int $field
     * @param int $order
     * @return PageData
     * @throws SQLException
     */
    public function getArticleList(?string $search, ?string $category, ?array $tags, ?int $page = 1, int $row = 10, int $field = 0, int $order = 0): PageData
    {
        $wants = $this->prepareReadFields(static::$showFields, '_:article');
        $parameter = [];
        $binder = [];
        if (is_array($tags) && count($tags) > 0) {
            $query = $this->buildTagArrayFilter($wants, $tags, $parameter);
        } else {
            $query = $this->buildSimple($wants, $parameter);
        }
        $name = $this->table->getName();
        $condition = ' `_:' . $name . '`.`status` = :publish';
        $binder['publish'] = ArticleTable::PUBLISH;
        $condition = $this->buildCategoryFilter($category, $condition, $binder);
        $condition = $this->buildSearchFilter($search, $condition, $binder);
        $query = $query . ' WHERE ' . $condition;
        $query .= $this->buildOrder($field, $order);
        $parameter = array_merge($binder, $parameter);
        return PageData::create($this->table->query($query, $parameter), $page, $row);
    }

    /**
     * @param int $field
     * @param int $order
     * @return string
     */
    protected function buildOrder(int $field = 0, int $order = 0)
    {
        $name = $this->table->getName();
        $query = ' ORDER BY `_:' . $name . '`.`stick` DESC';
        $orderType = $order == 0 ? 'DESC' : 'ASC';
        if ($field == 0) {
            $query .= ', `_:' . $name . '`.`modify_time` ' . $orderType;
        } else {
            $query .= ', `_:' . $name . '`.`create_time` ' . $orderType;
        }
        return $query;
    }

    /**
     * @param string|null $search
     * @param string $condition
     * @param array $binder
     * @return string
     */
    protected function buildSearchFilter(?string $search, string $condition, array &$binder): string
    {
        if ($search !== null && mb_strlen($search) >= 2) {
            $name = $this->table->getName();
            $condition = '`_:' . $name . '`.`title` LIKE :search AND ' . $condition;
            $binder['search'] = $this->buildSearch($search);
        }
        return $condition;
    }

    /**
     * @param string|null $category
     * @param string $condition
     * @param array $binder
     * @return string
     * @throws SQLException
     */
    protected function buildCategoryFilter(?string $category, string $condition, array &$binder): string
    {
        if ($category !== null) {
            if (is_numeric($category)) {
                $condition = '`category` = :category AND ' . $condition;
                $binder['category'] = $category;
            } else {
                $category = $this->categoryController->getTable()->read(['id'])->where(['slug' => $category]);
                $binder = array_merge($binder, $category->getBinder());
                $condition = '`category` = (' . $category . ') AND ' . $condition;
            }
        }
        return $condition;
    }

    /**
     * @param string $wants
     * @param array $binder
     * @return string
     */
    protected function buildSimple(string $wants, array &$binder): string
    {
        $articleName = $this->table->getName();
        $query = "SELECT " . $wants . " FROM _:" . $articleName;
        $binder = [];
        return $query;
    }


    /**
     * @param string $wants
     * @param array $tagId
     * @param array $binder
     * @return string
     */
    protected function buildTagArrayFilter(string $wants, array $tagId, array &$binder): string
    {
        $tag = $this->tagController->getTable();
        $tagTableName = $tag->getName();
        $tagRelate = $this->tagController->getRelationController()->getTable();
        $tagRelateTableName = $tagRelate->getName();
        $articleName = $this->table->getName();
        $query = "SELECT DISTINCT " . $wants . " FROM _:" . $tagTableName . " 
        JOIN _:" . $tagRelateTableName . " ON `_:" . $tagRelateTableName . "`.`item` IN (:tag)  
        JOIN _:" . $articleName . " ON `_:" . $articleName . "`.`id` = `_:" . $tagRelateTableName . "`.`relate`";
        $binder['tag'] = new ArrayObject($tagId);
        return $query;
    }

    /**
     * @return TagController
     */
    public function getTagController(): TagController
    {
        return $this->tagController;
    }

    /**
     * @return CategoryController
     */
    public function getCategoryController(): CategoryController
    {
        return $this->categoryController;
    }

    /**
     * 构建搜索语句
     *
     * @param string $search
     * @return string
     */
    protected function buildSearch(string $search): string
    {
        if (strlen($search) > 80) {
            $search = substr($search, 0, 80);
        }
        $search = str_replace('%', '', $search);
        $split = preg_split('/\s+/', $search);
        if (is_array($split)) {
            array_filter($split);
            return '%' . implode('%', $split) . '%';
        }
        return $search;
    }
}
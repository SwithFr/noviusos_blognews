<?php
/**
 * NOVIUS OS - Web OS for digital communication
 *
 * @copyright  2011 Novius
 * @license    GNU Affero General Public License v3 or (at your option) any later version
 *             http://www.gnu.org/licenses/agpl-3.0.html
 * @link http://www.novius-os.org
 */

namespace Nos\BlogNews;

use Nos\Controller_Front_Application;
use \Nos\Comments\Model_Comment;

use Fuel\Core\Inflector;
use View;

class Controller_Front extends Controller_Front_Application
{
    /**
     * @var \Nos\Pagination
     */
    public $pagination;
    public $current_page = 1;

    public $page_from = false;

    public $enhancerUrl_segments;

    public static $tag_class;
    public static $post_class;
    public static $category_class;

    public static function _init()
    {
        $namespace = \Inflector::get_namespace(get_called_class());
        static::$tag_class = $namespace.'Model_Tag';
        static::$post_class = $namespace.'Model_Post';
        static::$category_class = $namespace.'Model_Category';
    }

    public function before()
    {
        parent::before();
        $this->app_config = \Arr::merge($this->app_config, static::getGlobalConfiguration());

        // @todo voir l'extension des modules -> refactoring a faire au niveau generique
        list($application_name) = \Config::configFile(get_called_class());
        \Config::load('noviusos_blognews::controller/front', true);


        // We are manually merging configuration since we are not using the extend functionnality as intended.
        // In novius-os, if many application are extending one application, all configuration file on equivalent
        // paths are merged. Extend application tweek and add some functionnality to the existing application.
        // This is not what we want here since this is an headless application used by other application.
        // We do not want configuration files from different applications merged.
        $this->config = \Arr::merge(\Config::get('noviusos_blognews::controller/front'), \Config::extendable_load($application_name, 'controller/front'));
        $this->config['classes'] = array(
            'post' => static::$post_class,
            'tag' => static::$tag_class,
            'category' => static::$category_class,
        );

        parent::before();
    }

    public function after($response)
    {
        $this->main_controller->addMeta('<link rel="alternate" type="application/rss+xml" title="'.htmlspecialchars(__('Posts list')).'" href="'.$this->main_controller->getEnhancedUrlPath().'rss/posts.html">');
        $this->main_controller->addMeta('<link rel="alternate" type="application/rss+xml" title="'.htmlspecialchars(__('Comments list')).'" href="'.$this->main_controller->getEnhancedUrlPath().'rss/comments.html">');

        $this->main_controller->addCss('static/apps/noviusos_blognews/css/blognews.css');

        return parent::after($response);
    }


    public function action_main($args = array())
    {
        list($application_name) = \Config::configFile(get_called_class());

        $this->page_from = $this->main_controller->getPage();

        $this->config['item_per_page'] = (int) $args['item_per_page'];

        \View::set_global('enhancer_args', $args);

        $enhancer_url = $this->main_controller->getEnhancerUrl();

        if (!empty($enhancer_url)) {
            $this->enhancerUrl_segments = explode('/', $enhancer_url);
            $segments = $this->enhancerUrl_segments;

            if (empty($segments[1])) {
                return $this->display_item($args);
            } elseif ($segments[0] == 'stats') {

                $post = $this->_get_post(array(
                    'where' => array(
                        array('post_id', $segments[1]),
                    ),
                ));
                if (!empty($post)) {
                    $stats = \Session::get('noviusos_'.$application_name.'_stats', array());
                    if (!in_array($post->post_id, $stats)) {
                        $post->post_read++;
                        $post->save();
                        $stats[] = $post->post_id;
                        \Session::set('noviusos_'.$application_name.'_stats', $stats);
                        \Session::write();
                    }
                }
                \Nos\Tools_File::send(DOCROOT.'static/apps/noviusos_blognews/img/transparent.gif');

            } elseif ($segments[0] === 'page') {
                $this->init_pagination(empty($segments[1]) ? 1 : $segments[1]);

                return $this->display_list_main($args);
            } elseif ($segments[0] === 'author') {
                $this->init_pagination(!empty($segments[2]) ? $segments[2] : 1);

                return $this->display_list_author($args);
            } elseif ($segments[0] === 'tag') {
                $this->init_pagination(!empty($segments[2]) ? $segments[2] : 1);

                return $this->display_list_tag($args);
            } elseif ($segments[0] === 'category') {
                $this->init_pagination(!empty($segments[2]) ? $segments[2] : 1);

                return $this->display_list_category($args);
            } elseif ($segments[0] == 'rss') {
                $rss = \Nos\Tools_RSS::forge(array(
                        'link' => \Uri::base(false).$this->main_controller->getUrl(),
                        'language' => $this->page_from->page_lang,
                    ));

                $content = false;
                if ($segments[1] === 'posts') {
                    if (empty($segments[2])) {
                        $posts = $this->_get_post_list();
                        $rss->set(array(
                                'title' => __('Posts list'),
                                'description' => __('Posts list'),
                            ));
                    } elseif ($segments[2] === 'category' && !empty($segments[3])) {
                        $category = $this->_get_category($segments[3]);
                        $posts = $this->_get_post_list(array('category' => $category));
                        $rss->set(array(
                                'title' => __('Category posts list:').' '.$category->cat_title,
                                'description' => __('Category posts list:').' '.$category->cat_title,
                            ));
                    } elseif ($segments[2] === 'tag' && !empty($segments[3])) {
                        $tag = $this->_get_tag($segments[3]);
                        $posts = $this->_get_post_list(array('tag' => $tag));
                        $rss->set(array(
                                'title' => __('Tag posts list:').' '.$tag->tag_label,
                                'description' => __('Tag posts list:').' '.$tag->tag_label,
                            ));
                    } else {
                        throw new \Nos\NotFoundException();
                    }
                    $items = array();
                    foreach ($posts as $post) {
                        $items[] = static::_get_rss_post($post);
                    }
                    $rss->set_items($items);
                    $content = $rss->build();

                } elseif ($segments[1] === 'comments') {
                    if (empty($segments[2])) {
                        $rss->set(array(
                                'title' => __('Comments list'),
                                'description' => __('Comments list'),
                            ));

                        $comments = \Nos\Comments\Model_Comment::find('all', array(
                            'order_by' => array('comm_created_at' => 'DESC'),
                        ));
                    } else {
                        $post = $this->_get_post(array(
                            'where' => array(
                                array('post_virtual_name', '=', $segments[2]),
                                array('post_lang', '=', $this->page_from->page_lang),
                            ),
                            'related' => 'comments',
                            'order_by' => array('comments.comm_created_at' => 'DESC'),
                        ));
                        if (empty($post)) {
                            throw new \Nos\NotFoundException();
                        }

                        $rss->set(array(
                                'title' => __('Post comments list:').' '.$post->post_title,
                                'description' => __('Post comments list:').' '.$post->post_title,
                            ));

                        $comments = $post->comments;
                    }
                    $items = array();
                    foreach ($comments as $comment) {
                        $item = static::_get_rss_comment($comment);
                        if (!empty($item)) {
                            $items[] = $item;
                        }
                    }
                    $rss->set_items($items);
                    $content = $rss->build();
                }
                \Response::forge($content, 200, array(
                    'Content-Type' => 'application/xml',
                ))->send(true);
                \Event::shutdown();
                exit();

            }

            throw new \Nos\NotFoundException();
        }

        $this->init_pagination(1);

        \View::set_global('enhancer_args', array_map(function() {
            return null;
        }, $args));

        return $this->display_list_main($args);
    }

    public function action_home($args = array())
    {
        $this->page_from = $this->main_controller->getPage();
        $this->config['item_per_page'] = (int) $args['item_per_page'];

        return $this->display_list_main($args);
    }

    protected function init_pagination($page)
    {
        $this->current_page = $page;
        $this->pagination   = new \Nos\Pagination();
    }

    public function display_list_main($args)
    {
        $posts = $this->_get_post_list($args);

        return View::forge($this->config['views']['list'], array(
            'posts'       => $posts,
            'type'        => 'main',
            'item'        => 'main',
            'pagination' => $this->pagination
        ), false);
    }

    public function display_list_tag()
    {
        list(, $tag) = $this->enhancerUrl_segments;
        $tag = $this->_get_tag($tag);
        $posts = $this->_get_post_list(array('tag' => $tag));

        $this->main_controller->addMeta('<link rel="alternate" type="application/rss+xml" title="'.htmlspecialchars(__('Tag posts list:').' '.$tag->tag_label).'" href="'.$this->main_controller->getEnhancedUrlPath().'rss/posts/tag/'.urlencode($tag->tag_label).'.html">');

        return View::forge('noviusos_blognews::front/post/list', array(
            'posts'       => $posts,
            'type'        => 'tag',
            'item'        => $tag,
            'pagination' => $this->pagination,
        ), false);
    }

    public function display_list_category()
    {
        list(, $category) = $this->enhancerUrl_segments;
        $category = $this->_get_category($category);
        $posts = $this->_get_post_list(array('category' => $category));

        $this->main_controller->addMeta('<link rel="alternate" type="application/rss+xml" title="'.htmlspecialchars(__('Category posts list:').' '.$category->cat_title).'" href="'.$this->main_controller->getEnhancedUrlPath().'rss/posts/category/'.urlencode($category->cat_virtual_name).'.html">');

        return View::forge('noviusos_blognews::front/post/list', array(
            'posts'       => $posts,
            'type'        => 'category',
            'item'        => $category,
            'pagination' => $this->pagination,
        ), false);
    }

    /**
     * Display a single item (outside a list context)
     *
     * @param  type            $item_id
     * @return \Fuel\Core\View
     */
    public function display_item()
    {
        list($item_virtual_name) = $this->enhancerUrl_segments;
        $post = $this->_get_post(array(
            'where' => array(
                array('post_virtual_name', '=', $item_virtual_name),
                array('post_lang', '=', $this->page_from->page_lang),
            ),
        ));
        if (empty($post)) {
            throw new \Nos\NotFoundException();
        }

        $this->main_controller->addMeta('<link rel="alternate" type="application/rss+xml" title="'.htmlspecialchars(__('Post comments list:').' '.$post->post_title).'" href="'.$this->main_controller->getEnhancedUrlPath().'rss/comments/'.urlencode($post->post_virtual_name).'.html">');

        $page = $this->main_controller->getPage();
        $this->main_controller->setTitle($page->page_title . ' - ' . $post->post_title);
        $page->page_title = $post->post_title;
        $add_comment_success = 'none';
        if ($this->app_config['comments']['enabled'] && $this->app_config['comments']['can_post']) {
            if ($this->app_config['comments']['use_recaptcha']) {
                \Package::load('fuel-recatpcha', APPPATH.'packages/fuel-recaptcha/');
            }
            $add_comment_success = $this->_add_comment($post);
        }

        return View::forge($this->config['views']['item'], array(
            'add_comment_success'   => $add_comment_success,
            'item'                  => $post,
        ), false);
    }

    protected function _get_post($options = array())
    {
        $post_class = static::$post_class;

        return $post_class::get_first($options, $this->main_controller->isPreview());
    }

    protected function _get_category($category)
    {
        $category_class = static::$category_class;

        $category = $category_class::find('first', array('where' => array(
            array('cat_virtual_name', 'LIKE', strtolower($category),),
            array('cat_lang', '=', $this->page_from->page_lang),
        )));
        if (empty($category)) {
            throw new \Nos\NotFoundException();
        }

        return $category;
    }

    protected function _get_tag($tag)
    {
        $tag_class = static::$tag_class;

        $tag = $tag_class::find('first', array('where' => array(array(
            'tag_label', 'LIKE', strtolower($tag),
        ))));
        if (empty($tag)) {
            throw new \Nos\NotFoundException();
        }

        return $tag;
    }

    protected function _get_post_list($params = array())
    {
        $post_class = static::$post_class;

        // Apply language
        if (isset($this->page_from->page_lang)) {
            $params['lang'] = $this->page_from->page_lang;
        } else {
            $params['lang'] = key(\Config::get('locales'));
        }

        // Apply pagination
        if (isset($this->pagination)) {
            $this->pagination->set_config(array(
                    'total_items'    => $post_class::count_all($params),
                    'per_page'       => $this->config['item_per_page'],
                    'current_page'   => $this->current_page,
                ));
        }
        $params['offset']   = $this->pagination ? (int) $this->pagination->offset : 0;

        $params['limit']    = $this->config['item_per_page'];

        if (isset($params['cat_id'])) {
            $category_class = static::$category_class;
            if (!empty($params['category']) && $params['category']->cat_id != $params['cat_id']) {
                $params['categories'] = array($params['category'], $category_class::find($params['cat_id']));
                unset($params['category']);
            } else {
                $params['category'] = $category_class::find($params['cat_id']);
            }
        }
        if (isset($this->config['order_by'])) {
            $params['order_by'] = $this->config['order_by'];
        }

        // Get objects
        $posts = $post_class::get_all($params);

        return $posts;
    }

    protected static function _get_rss_post($post)
    {
        $content = $post->get_default_nuggets();
        $item = array();
        $item['title'] = isset($content[\Nos\DataCatcher::TYPE_TITLE]) ? $content[\Nos\DataCatcher::TYPE_TITLE] : $post->post_title;
        if (isset($content[\Nos\DataCatcher::TYPE_URL])) {
            // Nugget contains <page_id::item_slug>
            list($page_id, $itemPath) = explode('::', $content[\Nos\DataCatcher::TYPE_URL]);
            $urlPath = \Nos\Tools_Enhancer::url_page($page_id);
            $item['link'] = \Uri::base(false).$urlPath.$itemPath;
        } else {
            $item['link'] = \Uri::base(false).$post->url();
        }
        if (isset($content[\Nos\DataCatcher::TYPE_IMAGE])) {
            $item['img'] = \Uri::base(false).$content[\Nos\DataCatcher::TYPE_IMAGE];
        }
        $item['description'] = isset($content[\Nos\DataCatcher::TYPE_TEXT]) ? $content[\Nos\DataCatcher::TYPE_TEXT] : $post->post_summary;
        $item['pubDate'] = $post->post_created_at;
        $item['author'] = $post->author->fullname() ?: $post->post_author;

        return $item;
    }

    protected static function _get_rss_comment($comment)
    {
        $post_class = static::$post_class;
        $post = $post_class::find($comment->comm_foreign_id);
        if (empty($post)) {
            return null;
        }
        $item = array();
        $item['title'] = __('Post comments:').' '.$post->post_title;
        $content = $post->get_default_nuggets();
        if (isset($content[\Nos\DataCatcher::TYPE_URL])) {
            // Nugget contains <page_id::item_slug>
            list($page_id, $itemPath) = explode('::', $content[\Nos\DataCatcher::TYPE_URL]);
            $urlPath = \Nos\Tools_Enhancer::url_page($page_id);
            $item['link'] = \Uri::base(false).$urlPath.$itemPath;
        } else {
            $item['link'] = \Uri::base(false).$post->url();
        }
        $item['link'] .= '#comment'.$comment->comm_id;
        $item['description'] = $comment->comm_content;
        $item['pubDate'] = $comment->comm_created_at;
        $item['author'] = $comment->comm_author;

        return $item;
    }
    protected function url_stats($item)
    {
        return $this->main_controller->getEnhancedUrlPath().'stats/'.urlencode($item->post_id).'.html';
    }

    public static function get_url_model($item, $params = array())
    {
        $model = get_class($item);
        $page = isset($params['page']) ? $params['page'] : 1;

        switch ($model) {
            case static::$post_class:
                return urlencode($item->post_virtual_name).'.html';
                break;

            case static::$tag_class:
                return 'tag/'.urlencode($item->tag_label).($page > 1 ? '/'.$page : '').'.html';
                break;

            case static::$category_class:
                return 'category/'.urlencode($item->cat_virtual_name).($page > 1 ? '/'.$page : '').'.html';
                break;
        }

        return false;
    }

    protected function _add_comment($post)
    {
        if (\Input::post('todo') == 'add_comment' && \Input::post('ismm') == '327') {
            if (!$this->app_config['comments']['use_recaptcha'] || \ReCaptcha\ReCaptcha::instance()->check_answer(\Input::real_ip(), \Input::post('recaptcha_challenge_field'), \Input::post('recaptcha_response_field'))) {
                $post_class = static::$post_class;
                $comm = new Model_Comment();
                $comm->comm_from_table = $post_class::get_table_name();
                $comm->comm_email = \Input::post('comm_email');
                $comm->comm_author = \Input::post('comm_author');
                $comm->comm_content = \Input::post('comm_content');
                $date = new \Fuel\Core\Date();
                $comm->comm_created_at = \Date::forge()->format('mysql');
                $comm->comm_foreign_id = $post->post_id;
                $comm->comm_state = $this->config['comment_default_state'];
                $comm->comm_ip = \Input::ip();

                \Event::trigger_function('noviusos_blognews|front->_add_comment', array(&$comm, &$post));

                $comm->save();

                \Cookie::set('comm_email', \Input::post('comm_email'));
                \Cookie::set('comm_author', \Input::post('comm_author'));

                return true;
            } else {
                return false;
            }
        }

        return 'none'; // @todo: see if we can't return null
    }
}

<?php
/**
 * @link https://cms.skeeks.com/
 * @copyright Copyright (c) 2010 SkeekS
 * @license https://cms.skeeks.com/license/
 * @author Semenov Alexander <semenov@skeeks.com>
 */

namespace skeeks\cms\seo\vendor;


use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidParamException;
use yii\base\UserException;
use yii\helpers\ArrayHelper;
use yii\web\Application;
use yii\web\View;

/**
 * Class CanUrl
 *
 * @property string|null  scheme
 * @property string|null  schema
 *
 * @property string|null  user
 * @property string|null  login
 *
 * @property string|null  pass
 * @property string|null  password
 *
 * @property string|null  host
 *
 * @property string|null  port
 *
 * @property string|null  path
 *
 * @property array|null   query_params
 * @property array|null   core_params
 * @property string|null  query
 *
 * @property string|null  fragment
 * @property string|null  frag
 *
 * @property string       canurl
 *
 *
 * @property array        extra_tracked_methods
 * @property bool         is_track_ajax
 * @property bool         is_track_pjax
 * @property bool         is_track_flash
 *
 *
 * @property array        important_params
 * @property array        minor_params
 *
 * @property string|false redirurl
 *
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class CanUrl extends Component implements BootstrapInterface
{

    public $extra_tracked_methods = [];
    public $is_track_ajax = false;
    public $is_track_pjax = false;
    public $is_track_flash = false;
    protected $_scheme;
    protected $_user = '';
    protected $_pass = '';
    protected $_host;
    protected $_port = '';
    protected $_path;
    protected $_fragment = '';
    protected $_query_params;
    protected $_important_params;
    protected $_minor_params = [
        'from'         => null,
        '_openstat'    => null,
        'utm_source'   => null,
        'utm_medium'   => null,
        'utm_campaign' => null,
        'utm_content'  => null,
        'utm_term'     => null,
        'utm_referrer' => null,

        'pm_source'   => null,
        'pm_block'    => null,
        'pm_position' => null,


        'clid'       => null,
        'yclid'      => null,
        'ymclid'     => null,
        'frommarket' => null,
        'text'       => null,
    ];
    public function bootstrap($application)
    {
        if ($application instanceof Application) {
            $this->init_events();
        }
    }
    public function init_events()
    {
        \Yii::$app->getView()->on(View::EVENT_END_PAGE, [$this, 'event_end_page']);
        \Yii::$app->on(Application::EVENT_AFTER_REQUEST, [$this, 'event_after_request']);
    }
    public function event_end_page(Event $event)
    {

        $canurl = $this->GETcanurl();

        /** @var \yii\web\View $view */
        $view = $event->sender;
        $view->linkTags['canonical'] = '<link rel="canonical" href="'.$canurl.'"/>';

        $this->if_need_then_send_redirect(true);
    }
    public function GETcanurl()
    {
        $parsed_canurl = [];

        if (!isset($this->_scheme)) {
            throw new UserException('!isset($this->_scheme)');
        }
        if (!isset($this->_user)) {
            throw new UserException('!isset($this->_user)');
        }
        if (!isset($this->_pass)) {
            throw new UserException('!isset($this->_pass)');
        }
        if (!isset($this->_host)) {
            throw new UserException('!isset($this->_host)');
        }
        if (!isset($this->_port)) {
            throw new UserException('!isset($this->_port)');
        }
        if (!isset($this->_path)) {
            throw new UserException('!isset($this->_path)');
        }
        if (!isset($this->_fragment)) {
            throw new UserException('!isset($this->_fragment)');
        }

        if (!isset($this->_query_params)) {
            throw new UserException('!isset($this->_query_params)');
        }

        $parsed_canurl['scheme'] = $this->_scheme;
        $parsed_canurl['user'] = $this->_user;
        $parsed_canurl['pass'] = $this->_pass;
        $parsed_canurl['host'] = $this->_host;
        $parsed_canurl['port'] = $this->_port;
        $parsed_canurl['path'] = $this->_path;
        $parsed_canurl['fragment'] = $this->_fragment;

        $parsed_canurl['query'] = $this->GETquery();

        return UrlHelper::build_url($parsed_canurl);
    }
    public function GETquery()
    {
        if (!isset($this->_query_params)) {
            return null;
        }

        $this_query_params = $this->_query_params;
        ksort($this_query_params);
        $rett_params = $this_query_params;

        return UrlHelper::build_query($rett_params);
    }
    /**
     * @param string|null $current_url
     * @param bool        $is_final
     */
    public function if_need_then_send_redirect($is_final, $current_url = null)
    {

        if (!$this->is_tracked()) {
            return false;
        }

        $res = $this->is_need_redirect($is_final, $current_url);
        if ($res === false) {
            return false;
        }

        $response = \Yii::$app->getResponse();

        $response->getHeaders()->set('X-Skeeks-Seo-Can-Url', 'YES');
        $response->getHeaders()->set('Location', $res);
        $response->setStatusCode(301);
        $response->send();
        exit;
    }
    public function is_tracked()
    {
        $request = \Yii::$app->getRequest();

        $request_method = $request->getMethod();
        if (!in_array($request_method, ['GET', 'HEAD']) AND !in_array($request_method, $this->extra_tracked_methods)) {
            return false;
        }

        if (!$this->is_track_ajax AND $request->getIsAjax()) {
            return false;
        }
        if (!$this->is_track_pjax AND $request->getIsPjax()) {
            return false;
        }
        if (!$this->is_track_flash AND $request->getIsFlash()) {
            return false;
        }

        return true;
    }
    /**
     * @param string|null $current_url
     * @param bool        $is_final
     */
    public function is_need_redirect($is_final, $current_url = null)
    {
        if (!is_bool($is_final)) {
            throw new InvalidParamException('(!is_bool($throw_if_null))');
        }
        if (!isset($current_url)) {
            $current_url = \Yii::$app->getRequest()->getAbsoluteUrl();
        }
        if (!is_string($current_url)) {
            throw new InvalidParamException('(!is_string($current_url))');
        }

        $parsed_current_url = parse_url($current_url);

        $redirurl = $this->GETredirurl($parsed_current_url, $is_final);

        if ($redirurl === $current_url) {
            return false;
        }

        $current_url = UrlHelper::build_url($parsed_current_url);

        if ($redirurl === $current_url) {
            return false;
        }
        return $redirurl;
    }
    /**
     *
     * @param string|null $current_url
     * @param bool        $is_final
     * @return string
     */
    public function GETredirurl($current_url = null, $is_final = true)
    {
        if (!isset($current_url)) {
            $current_url = \Yii::$app->getRequest()->getAbsoluteUrl();
        }
        if (!is_string($current_url) AND !is_array($current_url)) {
            throw new InvalidParamException('(!is_string($current_url) AND !is_array($current_url))');
        } elseif (is_string($current_url)) {
            $parsed_current_url = parse_url($current_url);
        } elseif (is_array($current_url)) {
            $parsed_current_url = $current_url;
        } else {
            throw new Exception('!!!');
        }
        if (!is_bool($is_final)) {
            throw new InvalidParamException('(!is_bool($is_final))');
        }


        $parsed_redirurl = [];

        if ($is_final) {
            if (!isset($this->_scheme)) {
                throw new UserException('!isset($this->scheme)');
            }
            if (!isset($this->_user)) {
                throw new UserException('!isset($this->user)');
            }
            if (!isset($this->_pass)) {
                throw new UserException('!isset($this->pass)');
            }
            if (!isset($this->_host)) {
                throw new UserException('!isset($this->host)');
            }
            if (!isset($this->_port)) {
                throw new UserException('!isset($this->port)');
            }
            if (!isset($this->_path)) {
                throw new UserException('!isset($this->path)');
            }
            if (!isset($this->_fragment)) {
                throw new UserException('!isset($this->fragment)');
            }

            if (!isset($this->_query_params)) {
                throw new UserException('!isset($this->query_params)');
            }
        }

        $parsed_redirurl['scheme'] = (isset($this->_scheme) ? $this->_scheme : ArrayHelper::getValue($parsed_current_url, 'scheme'));
        $parsed_redirurl['user'] = (isset($this->_user) ? $this->_user : ArrayHelper::getValue($parsed_current_url, 'user'));
        $parsed_redirurl['pass'] = (isset($this->_pass) ? $this->_pass : ArrayHelper::getValue($parsed_current_url, 'pass'));
        $parsed_redirurl['host'] = (isset($this->_host) ? $this->_host : ArrayHelper::getValue($parsed_current_url, 'host'));
        $parsed_redirurl['port'] = (isset($this->_port) ? $this->_port : ArrayHelper::getValue($parsed_current_url, 'port'));
        $parsed_redirurl['path'] = (isset($this->_path) ? $this->_path : ArrayHelper::getValue($parsed_current_url, 'path'));
        $parsed_redirurl['fragment'] = (isset($this->_fragment) ? $this->_fragment : ArrayHelper::getValue($parsed_current_url, 'fragment'));

        $parsed_redirurl['query'] = $this->make_query_for_redirurl($parsed_current_url, $is_final);
        $parsed_redirurl['query'] = (isset($parsed_redirurl['query']) ? $parsed_redirurl['query'] : ArrayHelper::getValue($parsed_current_url, 'query'));

        $redirurl = UrlHelper::build_url($parsed_redirurl);

        return $redirurl;
    }
    public function make_query_for_redirurl($current_url = null, $is_final = true)
    {
        if (!isset($current_url)) {
            $current_url = \Yii::$app->getRequest()->getAbsoluteUrl();
        }
        if (!is_string($current_url) AND !is_array($current_url)) {
            throw new InvalidParamException('(!is_string($current_url) AND !is_array($current_url))');
        } elseif (is_string($current_url)) {
            $parsed_current_url = parse_url($current_url);
        } elseif (is_array($current_url)) {
            $parsed_current_url = $current_url;
        } else {
            throw new Exception('!!!');
        }
        if (!is_bool($is_final)) {
            throw new InvalidParamException('(!is_bool($is_final))');
        }

        $current_params = [];
        if (empty($parsed_current_url['query'])) {
            $current_params = [];
        } else {
            parse_str($parsed_current_url['query'], $current_params);
        }


        if (!isset($this->_query_params)) {
            return null;
        }
        if (!isset($this->_important_params) AND !$is_final) {
            return null;
        }
        if (!isset($this->_minor_params) AND !$is_final) {
            return null;
        }

        $this_query_params = $this->_query_params;
        ksort($this_query_params);
        $rett_params = $this_query_params;

        $this_important_params = (isset($this->_important_params) ? $this->_important_params : []);
        ksort($this_important_params);
        foreach ($this_important_params as $kkk => $vvv) {
            if (!isset($vvv) AND array_key_exists($kkk, $current_params)) {
                $vvv = $current_params[$kkk];
            }
            $rett_params[$kkk] = $vvv;
        }

        $this_minor_params = (isset($this->_minor_params) ? $this->_minor_params : []);
        ksort($this_minor_params);
        foreach ($this_minor_params as $kkk => $vvv) {
            if (!isset($vvv) AND array_key_exists($kkk, $current_params)) {
                $vvv = $current_params[$kkk];
            }
            $rett_params[$kkk] = $vvv;
        }

        return UrlHelper::build_query($rett_params);
    }
    public function event_after_request(Event $event)
    {
        $this->if_need_then_send_redirect(true);
    }
    public function GETscheme() { return $this->_scheme; }
    public function SETscheme($value)
    {
        $this->_scheme = $value;
        return $this;
    }
    public function SETschema($value)
    {
        $this->_scheme = $value;
        return $this;
    }
    public function GETschema() { return $this->_scheme; }
    public function GETuser() { return $this->_user; }
    public function SETuser($value)
    {
        $this->_user = $value;
        return $this;
    }
    public function SETlogin($value)
    {
        $this->_user = $value;
        return $this;
    }
    public function GETlogin() { return $this->_user; }
    public function GETpass() { return $this->_pass; }
    public function SETpass($value)
    {
        $this->_pass = $value;
        return $this;
    }
    public function SETpassword($value)
    {
        $this->_pass = $value;
        return $this;
    }
    public function GETpassword() { return $this->_pass; }
    public function GEThost() { return $this->_host; }
    public function SEThost($value)
    {
        $this->_host = $value;
        return $this;
    }
    public function SETdomain($value)
    {
        $this->_host = $value;
        return $this;
    }
    public function GETdomain() { return $this->_host; }
    public function GETport() { return $this->_port; }
    public function SETport($value)
    {
        $this->_port = $value;
        return $this;
    }
    public function GETpath() { return $this->_path; }
    public function SETpath($value)
    {
        $this->_path = $value;
        return $this;
    }
    public function GETfragment() { return $this->_fragment; }
    public function SETfragment($value)
    {
        $this->_fragment = $value;
        return $this;
    }
    public function SETfrag($value)
    {
        $this->_fragment = $value;
        return $this;
    }
    public function GETfrag() { return $this->_fragment; }
    public function SETcore_params($core_params) { return $this->SETquery_params($core_params); }
    public function GETcore_params() { return $this->GETquery_params(); }
    public function GETquery_params()
    {
        if (isset($this->_query_params) AND !is_array($this->_query_params)) {
            throw new Exception('(isset($this->_query_params) AND !is_array($this->_query_params))');
        }
        return $this->_query_params;
    }
    public function SETquery_params($query_params)
    {
        if (isset($query_params) AND empty($query_params)) {
            $query_params = [];
        }
        if (isset($query_params) AND !is_array($query_params)) {
            throw new InvalidParamException('(isset($query_params) AND !is_array($query_params))');
        }
        $this->_query_params = $query_params;
        return $this;
    }
    public function ADDcore_params($add_core_params) { return $this->ADDquery_params($add_core_params); }
    public function ADDquery_params($add_query_params)
    {
        if (empty($add_query_params)) {
            $add_query_params = [];
        }
        if (!is_array($add_query_params)) {
            throw new InvalidParamException('(!is_array($add_query_params))');
        }
        $this_query_params = $this->GETquery_params();
        if (!isset($this_query_params)) {
            $this_query_params = $add_query_params;
        } else {
            $this_query_params = array_merge($this_query_params, $add_query_params);
        }
        return $this->SETquery_params($this_query_params);
    }
    public function SETimportant_pnames($important_pnames)
    {
        if (empty($important_pnames)) {
            $important_pnames = [];
        }
        if (is_string($important_pnames)) {
            $important_pnames = [$important_pnames];
        }
        if (!is_array($important_pnames)) {
            throw new InvalidParamException('(!is_array($important_pnames))');
        }
        $important_pnames = array_unique($important_pnames);
        $important_pnames = array_values($important_pnames);
        $important_params = array_fill_keys($important_pnames, null);
        return $this->SETimportant_params($important_params);
    }
    public function ADDimportant_pname($add_important_pname) { return $this->ADDimportant_pnames($add_important_pname); }
    public function ADDimportant_pnames($add_important_pnames)
    {
        if (empty($add_important_pnames)) {
            $add_important_pnames = [];
        }
        if (is_string($add_important_pnames)) {
            $add_important_pnames = [$add_important_pnames];
        }
        if (!is_array($add_important_pnames)) {
            throw new InvalidParamException('(!is_array($add_important_pnames))');
        }
        $add_important_pnames = array_unique($add_important_pnames);
        $add_important_pnames = array_values($add_important_pnames);
        $add_important_params = array_fill_keys($add_important_pnames, null);
        return $this->ADDimportant_params($add_important_params);
    }
    public function ADDimportant_params($add_important_params)
    {
        if (empty($add_important_params)) {
            $add_important_params = [];
        }
        if (!is_array($add_important_params)) {
            throw new InvalidParamException('(!is_array($add_important_params))');
        }
        $this_important_params = $this->GETimportant_params();
        if (!isset($this_important_params)) {
            $this_important_params = $add_important_params;
        } else {
            $this_important_params = array_merge($this_important_params, $add_important_params);
        }
        return $this->SETimportant_params($this_important_params);
    }
    public function GETimportant_params()
    {
        if (isset($this->_important_params) AND !is_array($this->_important_params)) {
            throw new Exception('(isset($this->_important_params) AND !is_array($this->_important_params))');
        }
        return $this->_important_params;
    }
    public function SETimportant_params($important_params)
    {
        if (isset($important_params) AND empty($important_params)) {
            $important_params = [];
        }
        if (isset($important_params) AND !is_array($important_params)) {
            throw new InvalidParamException('(isset($important_params) AND !is_array($important_params))');
        }
        $this->_important_params = $important_params;
        return $this;
    }
    public function SETminor_pnames($minor_pnames)
    {
        if (empty($minor_pnames)) {
            $minor_pnames = [];
        }
        if (is_string($minor_pnames)) {
            $minor_pnames = [$minor_pnames];
        }
        if (!is_array($minor_pnames)) {
            throw new InvalidParamException('(!is_array($minor_pnames))');
        }
        $minor_pnames = array_unique($minor_pnames);
        $minor_pnames = array_values($minor_pnames);
        $minor_params = array_fill_keys($minor_pnames, null);
        return $this->SETminor_params($minor_params);
    }
    public function ADDminor_pname($add_minor_pname) { return $this->ADDminor_pnames($add_minor_pname); }
    public function ADDminor_pnames($add_minor_pnames)
    {
        if (empty($add_minor_pnames)) {
            $add_minor_pnames = [];
        }
        if (is_string($add_minor_pnames)) {
            $add_minor_pnames = [$add_minor_pnames];
        }
        if (!is_array($add_minor_pnames)) {
            throw new InvalidParamException('(!is_array($add_minor_pnames))');
        }
        $add_minor_pnames = array_unique($add_minor_pnames);
        $add_minor_pnames = array_values($add_minor_pnames);
        $add_minor_params = array_fill_keys($add_minor_pnames, null);
        return $this->ADDminor_params($add_minor_params);
    }
    public function ADDminor_params($add_minor_params)
    {
        if (empty($add_minor_params)) {
            $add_minor_params = [];
        }
        if (!is_array($add_minor_params)) {
            throw new InvalidParamException('(!is_array($add_minor_params))');
        }
        $this_minor_params = $this->GETminor_params();
        if (!isset($this_minor_params)) {
            $this_minor_params = $add_minor_params;
        } else {
            $this_minor_params = array_merge($this_minor_params, $add_minor_params);
        }
        return $this->SETminor_params($this_minor_params);
    }
    public function GETminor_params()
    {
        if (isset($this->_minor_params) AND !is_array($this->_minor_params)) {
            throw new Exception('(isset($this->_minor_params) AND !is_array($this->_minor_params))');
        }
        return $this->_minor_params;
    }
    public function SETminor_params($minor_params)
    {
        if (isset($minor_params) AND empty($minor_params)) {
            $minor_params = [];
        }
        if (isset($minor_params) AND !is_array($minor_params)) {
            throw new InvalidParamException('(isset($minor_params) AND !is_array($minor_params))');
        }
        $this->_minor_params = $minor_params;
        return $this;
    }


}
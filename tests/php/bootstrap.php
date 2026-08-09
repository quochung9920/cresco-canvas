<?php
/** Minimal WordPress function surface for isolated PHP unit tests. */

define( 'ABSPATH', __DIR__ . '/' );
define( 'CRESCO_CANVAS_SCHEMA_VERSION', 4 );
define( 'CRESCO_CANVAS_MINIMUM_PHP', '8.1' );
define( 'CRESCO_CANVAS_MINIMUM_WORDPRESS', '6.7' );
define( 'CRESCO_CANVAS_PATH', dirname( __DIR__, 2 ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['cresco_test_options'] = array();
$GLOBALS['cresco_test_posts'] = array();
$GLOBALS['cresco_test_post_meta'] = array();
$GLOBALS['cresco_test_user_meta'] = array();
$GLOBALS['cresco_test_updates'] = array();
$GLOBALS['cresco_test_registered_meta'] = array();
$GLOBALS['cresco_test_capabilities'] = array();
$GLOBALS['cresco_test_transients'] = array();
$GLOBALS['cresco_test_routes'] = array();
$GLOBALS['cresco_test_filters'] = array();
$GLOBALS['cresco_test_actions'] = array();
$GLOBALS['cresco_test_is_multisite'] = false;
$GLOBALS['cresco_test_sites'] = array();
$GLOBALS['cresco_test_current_blog_id'] = 1;
$GLOBALS['cresco_test_blog_stack'] = array();
$GLOBALS['cresco_test_scheduled'] = array();

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code; private $message; private $data;
		public function __construct( $code = '', $message = '', $data = null ) { $this->code = $code; $this->message = $message; $this->data = $data; }
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
		public function get_error_data() { return $this->data; }
	}
}
if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server { const READABLE = 'GET'; const CREATABLE = 'POST'; const EDITABLE = 'POST,PUT,PATCH'; const DELETABLE = 'DELETE'; }
}
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request implements ArrayAccess {
		private $params; private $route; private $method; private $body; private $headers; private $files; private $body_params;
		public function __construct( $params = array(), $route = '', $method = 'GET', $body = '', $headers = array(), $files = array(), $body_params = array() ) { $this->params=$params; $this->route=$route; $this->method=$method; $this->body=$body; $this->headers=array_change_key_case($headers,CASE_LOWER); $this->files=$files; $this->body_params=$body_params; }
		public function get_param( $key ) { return $this->params[$key] ?? $this->body_params[$key] ?? null; }
		public function get_json_params() { return $this->params; }
		public function get_body_params() { return $this->body_params; }
		public function get_file_params() { return $this->files; }
		public function get_route() { return $this->route; }
		public function get_method() { return $this->method; }
		public function get_body() { return $this->body; }
		public function get_header( $name ) { return $this->headers[strtolower($name)] ?? ''; }
		public function offsetExists( $offset ): bool { return isset($this->params[$offset]); }
		public function offsetGet( $offset ): mixed { return $this->params[$offset] ?? null; }
		public function offsetSet( $offset, $value ): void { $this->params[$offset]=$value; }
		public function offsetUnset( $offset ): void { unset($this->params[$offset]); }
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response { private $data; private $status; private $headers=array(); public function __construct( $data=null, $status=200 ){ $this->data=$data; $this->status=$status; } public function get_data(){return $this->data;} public function get_status(){return $this->status;} public function header($name,$value){$this->headers[(string)$name]=(string)$value;} public function get_headers(){return $this->headers;} }
}
if ( ! class_exists( 'WP_Query' ) ) {
	class WP_Query {
		public $posts=array(); public $max_num_pages=0; public $found_posts=0;
		public function __construct( $args=array() ) {
			$all=array_values($GLOBALS['cresco_test_posts']);
			$all=array_values(array_filter($all, static function($post) use($args){ if(isset($args['post_type']) && $post->post_type!==$args['post_type']) return false; if(isset($args['post_status']) && 'any'!==$args['post_status'] && $post->post_status!==$args['post_status']) return false; return true; }));
			$this->found_posts=count($all); $per=max(1,(int)($args['posts_per_page']??10)); $page=max(1,(int)($args['paged']??1)); $this->max_num_pages=(int)ceil($this->found_posts/$per); $this->posts=array_slice($all,($page-1)*$per,$per);
		}
	}
}

function __( $text ){ return $text; }
function esc_html__( $text ){ return $text; }
function absint( $value ){ return abs((int)$value); }
function add_action( $hook, $callback, $priority=10, $accepted_args=1 ){ $GLOBALS['cresco_test_actions'][$hook][]=$callback; return true; }
function add_filter( $hook, $callback, $priority=10, $accepted_args=1 ){ $GLOBALS['cresco_test_filters'][$hook][]=$callback; return true; }
function do_action( $hook, ...$args ){ foreach($GLOBALS['cresco_test_actions'][$hook]??array() as $callback) call_user_func_array($callback,$args); }
function apply_filters( $hook, $value, ...$args ){ foreach($GLOBALS['cresco_test_filters'][$hook]??array() as $callback) $value=call_user_func_array($callback,array_merge(array($value),$args)); return $value; }
function add_option( $name, $value ){ if(array_key_exists($name,$GLOBALS['cresco_test_options'])) return false; $GLOBALS['cresco_test_options'][$name]=$value; return true; }
function update_option( $name, $value ){ $GLOBALS['cresco_test_options'][$name]=$value; return true; }
function get_option( $name, $default=false ){ return $GLOBALS['cresco_test_options'][$name]??$default; }
function delete_option( $name ){ unset($GLOBALS['cresco_test_options'][$name]); return true; }
function set_transient( $key,$value,$ttl=0 ){ $GLOBALS['cresco_test_transients'][$key]=$value; return true; }
function get_transient( $key ){ return $GLOBALS['cresco_test_transients'][$key]??false; }
function delete_transient( $key ){ unset($GLOBALS['cresco_test_transients'][$key]); return true; }
function delete_metadata( $meta_type,$object_id,$meta_key,$meta_value='',$delete_all=false ){ if('user'===$meta_type&&$delete_all){ foreach($GLOBALS['cresco_test_user_meta'] as &$values) unset($values[$meta_key]); unset($values); } return true; }
function delete_post_meta_by_key( $meta_key ){ foreach($GLOBALS['cresco_test_post_meta'] as &$values) unset($values[$meta_key]); unset($values); return true; }
function delete_post_meta( $post_id,$key ){ unset($GLOBALS['cresco_test_post_meta'][$post_id][$key]); return true; }
function update_post_meta( $post_id,$key,$value ){ $GLOBALS['cresco_test_post_meta'][$post_id][$key]=$value; return true; }
function get_bloginfo( $field ){ return 'version'===$field?'7.0.1':''; }
function get_current_user_id(){ return 7; }
function get_current_blog_id(){ return (int)$GLOBALS['cresco_test_current_blog_id']; }
function current_time( $type='mysql' ){ return 'timestamp'===$type ? time() : gmdate('Y-m-d H:i:s'); }
function current_user_can( $capability, ...$args ){ unset($args); return $GLOBALS['cresco_test_capabilities'][$capability]??true; }
function get_post( $post_id ){ return $GLOBALS['cresco_test_posts'][$post_id]??null; }
function get_post_type( $post_id ){ $post=get_post($post_id); return $post->post_type??''; }
function get_preview_post_link( $post ){ return 'https://example.test/?preview_id='.(int)$post->ID; }
function get_the_title( $post ){ if(is_numeric($post)) $post=get_post((int)$post); return (string)($post->post_title??''); }
function get_post_meta( $post_id,$key,$single=true ){ unset($single); return $GLOBALS['cresco_test_post_meta'][$post_id][$key]??''; }
function get_user_meta( $user_id,$key ){ return $GLOBALS['cresco_test_user_meta'][$user_id][$key]??''; }
function is_wp_error( $value ){ return $value instanceof WP_Error; }
function mysql2date( $format,$date ){ return gmdate($format,strtotime($date.' UTC')); }
function rest_sanitize_boolean( $value ){ return filter_var($value,FILTER_VALIDATE_BOOLEAN); }
function register_post_meta( $post_type,$meta_key,$args ){ $GLOBALS['cresco_test_registered_meta'][$post_type.':'.$meta_key]=$args; return true; }
function register_rest_route( $namespace,$route,$args ){ $GLOBALS['cresco_test_routes'][$namespace.$route]=$args; return true; }
function sanitize_hex_color( $value ){ return is_string($value)&&preg_match('/^#[0-9a-fA-F]{6}$/',$value)?strtolower($value):null; }
function sanitize_key( $value ){ return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$value)); }
function sanitize_title( $value ){ return sanitize_key( $value ); }
function sanitize_text_field( $value ){ return trim(strip_tags((string)$value)); }
function sanitize_textarea_field( $value ){ return trim(str_replace("\r","",strip_tags((string)$value))); }
function sanitize_email( $value ){ return filter_var(trim((string)$value),FILTER_SANITIZE_EMAIL); }
function is_email( $value ){ return false!==filter_var((string)$value,FILTER_VALIDATE_EMAIL); }
function sanitize_file_name( $value ){ $value=basename((string)$value); return preg_replace('/[^A-Za-z0-9._-]/','-',$value); }
function sanitize_mime_type( $value ){ return preg_replace('/[^a-z0-9.+\/-]/i','',(string)$value); }
function wp_strip_all_tags( $value ){ return strip_tags((string)$value); }
function wp_json_encode( $value, $flags=0 ){ return json_encode($value,$flags); }
function wp_salt( $scheme='auth' ){ return 'test-salt-'.$scheme; }
function wp_unslash( $value ){ return $value; }
function wp_parse_url( $url,$component=-1 ){ return parse_url($url,$component); }
function wp_http_validate_url( $url ){ return false!==filter_var($url,FILTER_VALIDATE_URL); }
function esc_url_raw( $url,$protocols=null ){ unset($protocols); return filter_var((string)$url,FILTER_SANITIZE_URL); }
function wp_normalize_path( $path ){ return str_replace('\\','/',(string)$path); }
function trailingslashit( $path ){ return rtrim((string)$path,'/\\').'/'; }
function untrailingslashit( $path ){ return rtrim((string)$path,'/\\'); }
function wp_check_filetype_and_ext( $path,$name,$mimes=array() ){ unset($path); $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); foreach($mimes as $extensions=>$mime){ $parts=explode('|',$extensions); if(in_array($ext,$parts,true)) return array('ext'=>$ext,'type'=>$mime,'proper_filename'=>false); } return array('ext'=>false,'type'=>false,'proper_filename'=>false); }
function wp_get_image_mime( $path ){ $f=new finfo(FILEINFO_MIME_TYPE); $mime=(string)$f->file($path); return str_starts_with($mime,'image/')?$mime:false; }
function parse_blocks( $content ){ $blocks=array(); if(preg_match_all('/<!--\s+wp:([a-zA-Z0-9_\/-]+)/',$content,$matches)){ foreach($matches[1] as $name) $blocks[]=array('blockName'=>$name,'attrs'=>array(),'innerBlocks'=>array()); } return $blocks; }
function wp_insert_post( $args,$wp_error=false ){ unset($wp_error); $id=$GLOBALS['cresco_test_posts']?max(array_keys($GLOBALS['cresco_test_posts']))+1:1; $GLOBALS['cresco_test_posts'][$id]=(object)array('ID'=>$id,'post_type'=>$args['post_type']??'post','post_status'=>$args['post_status']??'draft','post_title'=>$args['post_title']??'','post_date_gmt'=>gmdate('Y-m-d H:i:s')); return $id; }
function wp_delete_post( $post_id,$force=false ){ unset($force); $post=$GLOBALS['cresco_test_posts'][$post_id]??false; unset($GLOBALS['cresco_test_posts'][$post_id],$GLOBALS['cresco_test_post_meta'][$post_id]); return $post; }
function wp_delete_attachment( $post_id,$force=false ){ return wp_delete_post($post_id,$force); }
function get_posts( $args=array() ){
	$items=array_values($GLOBALS['cresco_test_posts']);
	$items=array_values(array_filter($items, static function($post) use($args){
		if(isset($args['post_type'])){ $types=(array)$args['post_type']; if(!in_array($post->post_type,$types,true)) return false; }
		if(isset($args['post_status']) && 'any'!==$args['post_status'] && $post->post_status!==$args['post_status']) return false;
		if(isset($args['meta_key'])){ $value=$GLOBALS['cresco_test_post_meta'][$post->ID][$args['meta_key']]??null; if(isset($args['meta_value']) && (string)$value!==(string)$args['meta_value']) return false; }
		return true;
	}));
	$limit=(int)($args['posts_per_page']??count($items)); if($limit>0) $items=array_slice($items,0,$limit);
	return (($args['fields']??'')==='ids')?array_map(static fn($p)=>(int)$p->ID,$items):$items;
}
function register_post_type(){ return true; }
function is_multisite(){ return (bool)$GLOBALS['cresco_test_is_multisite']; }
function get_sites( $args=array() ){ $ids=array_values($GLOBALS['cresco_test_sites']); $offset=max(0,(int)($args['offset']??0)); $number=(int)($args['number']??count($ids)); if($number<=0)$number=count($ids); $ids=array_slice($ids,$offset,$number); return (($args['fields']??'')==='ids')?$ids:array_map(static fn($id)=>(object)array('blog_id'=>$id),$ids); }
function switch_to_blog( $site_id ){ $GLOBALS['cresco_test_blog_stack'][]=$GLOBALS['cresco_test_current_blog_id']; $GLOBALS['cresco_test_current_blog_id']=(int)$site_id; return true; }
function restore_current_blog(){ if(!$GLOBALS['cresco_test_blog_stack'])return false; $GLOBALS['cresco_test_current_blog_id']=(int)array_pop($GLOBALS['cresco_test_blog_stack']); return true; }
function wp_next_scheduled( $hook ){ return $GLOBALS['cresco_test_scheduled'][$hook]??false; }
function wp_schedule_event( $timestamp,$recurrence,$hook,$args=array() ){ unset($recurrence,$args); $GLOBALS['cresco_test_scheduled'][$hook]=(int)$timestamp; return true; }
function wp_unschedule_hook( $hook ){ unset($GLOBALS['cresco_test_scheduled'][$hook]); return true; }

require_once CRESCO_CANVAS_PATH . 'includes/Autoloader.php';
CrescoCanvas\Autoloader::register();

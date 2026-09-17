<?php
/* Giả lập tối thiểu WordPress để chạy thử logic cấp tài khoản của plugin */
define('ABSPATH', '/tmp/fakewp/');
$GLOBALS['_users'] = [];      // email => object
$GLOBALS['_meta']  = [];
$GLOBALS['_opts']  = [];
$GLOBALS['_docs']  = ['staff' => []];

function add_action(){} function add_filter(){} function add_shortcode(){}
function register_activation_hook(){} function add_menu_page(){} function add_submenu_page(){}
function plugin_dir_path($f){ return dirname($f).'/'; }
function plugin_dir_url($f){ return 'https://site.test/wp-content/plugins/khh-platform/'; }
function home_url($p=''){ return 'https://site.test'.$p; }
function admin_url($p=''){ return 'https://site.test/wp-admin/'.$p; }
function rest_url($p=''){ return 'https://site.test/wp-json/'.$p; }
function wp_create_nonce($a){ return 'nonce-'.md5($a); }
function wp_nonce_field($a='',$n='_wpnonce'){ echo '<input type="hidden" name="'.$n.'" value="'.wp_create_nonce($a).'">'; }
function wp_nonce_url($u,$a=''){
  /* Bản thật: esc_html( add_query_arg(...) ) — esc_html đổi & thành &amp;
     Đúng chuỗi đã làm hỏng đường dẫn Thoát trên khmatrix.com. */
  $u .= (strpos($u,'?')===false?'?':'&').'_wpnonce='.wp_create_nonce($a);
  return str_replace('&','&amp;',$u); }
function add_query_arg($args,$url){
  $q = http_build_query($args);
  return $url . (strpos($url,'?')===false?'?':'&') . $q; }
function wp_get_current_user(){ $u=new stdClass(); $u->ID=1; $u->display_name='Minh Thư';
  $u->user_login='mnnv2kvc0008'; $u->roles=['khh_staff']; return $u; }
function esc_url_raw($u){ return $u; } function esc_url($u){ return $u; }
function esc_html($t){ return htmlspecialchars($t); } function esc_attr($t){ return htmlspecialchars($t); }
function sanitize_text_field($t){ return trim(strip_tags($t)); }
function sanitize_email($e){ return trim($e); }
function sanitize_user($u,$strict=false){ return preg_replace('/[^a-z0-9._-]/i','',$u); }
function sanitize_key($k){ return preg_replace('/[^a-z0-9_]/','',strtolower($k)); }
function is_email($e){ return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
function wp_unslash($v){ return $v; }
function wp_json_encode($v){ return json_encode($v, JSON_UNESCAPED_UNICODE); }
function wp_generate_password($l=12){ return str_repeat('x',$l); }
function wp_rand($a,$b){ return $a; }
function wp_parse_args($a,$d){ return array_merge($d,(array)$a); }
function get_option($k,$d=false){ return $GLOBALS['_opts'][$k] ?? $d; }
function update_option($k,$v){ $GLOBALS['_opts'][$k]=$v; return true; }
function get_current_user_id(){ return 1; }
function is_user_logged_in(){ return true; }
function current_user_can($c){ return true; }
function update_user_meta($id,$k,$v){ $GLOBALS['_meta'][$id][$k]=$v; return true; }
function get_user_meta($id,$k,$single=true){ return $GLOBALS['_meta'][$id][$k] ?? ''; }
function user_can($id,$c){ return false; }
function get_userdata($id){ foreach($GLOBALS['_users'] as $u){ if($u->ID==$id) return $u; } return null; }
function get_user_by($f,$v){
  foreach($GLOBALS['_users'] as $u){ if($f==='email'&&strtolower($u->user_email)===strtolower($v))return $u;
    if($f==='id'&&$u->ID==$v)return $u; } return false; }
function get_users($args=[]){ return array_values($GLOBALS['_users']); }
function username_exists($l){ foreach($GLOBALS['_users'] as $u){ if($u->user_login===$l) return $u->ID; } return false; }
function wp_insert_user($a){
  static $next=10; $u=(object)$a; $u->ID=$next++; $u->roles=[$a['role']];
  $u->display_name=$a['display_name']??''; $GLOBALS['_users'][$u->user_email]=$u; return $u->ID; }
function wp_new_user_notification(){ $GLOBALS['_mailed'] = true; }
function wp_remote_get($url,$a=[]){ return $GLOBALS['_google_response']; }
function wp_remote_retrieve_response_code($r){ return $r['code']; }
function wp_remote_retrieve_body($r){ return $r['body']; }
function is_wp_error($t){ return $t instanceof WP_Error; }
class WP_Error { public $c,$m; function __construct($c='',$m=''){ $this->c=$c; $this->m=$m; }
  function get_error_message(){ return $this->m; } }
/* vai trò + kho tệp */
$GLOBALS['_roles'] = [];
class WP_Role {
  public $name, $capabilities;
  function __construct($n,$c){ $this->name=$n; $this->capabilities=$c; }
  function has_cap($c){ return !empty($this->capabilities[$c]); }
  function add_cap($c,$g=true){ $this->capabilities[$c]=$g; }
}
function add_role($slug,$nhan,$caps){
  /* Bản thật KHÔNG làm gì khi vai trò đã tồn tại — chính chỗ này làm quyền mới
     không bao giờ tới được bản cài từ trước. */
  if (isset($GLOBALS['_roles'][$slug])) return null;
  return $GLOBALS['_roles'][$slug] = new WP_Role($slug, $caps);
}
function get_role($slug){ return $GLOBALS['_roles'][$slug] ?? null; }
function wp_max_upload_size(){ return $GLOBALS['_maxup'] ?? 2*1024*1024; }
function wp_upload_dir(){ return $GLOBALS['_updir'] ?? ['basedir'=>'/var/www/wp-content/uploads','error'=>false]; }
function wp_is_writable($p){ return $GLOBALS['_ghiduoc'] ?? true; }
function size_format($n){ return round($n/1048576,1).' MB'; }

/* dữ liệu: thay hai hàm đọc/ghi bằng mảng trong bộ nhớ */
function khh_table(){ return 'wp_khh_docs'; }
function khh_get_coll($c){ return $GLOBALS['_docs'][$c] ?? []; }
function khh_put_doc($c,$i,$b){ $GLOBALS['_docs'][$c][$i]=$b; return true; }

/* Phần "Tài khoản của nhân viên" nằm ở tệp riêng nhưng khh_settings() gọi thẳng vào nó,
   nên phải nạp trước — bỏ đi là lỗi "undefined function" chứ không phải phép thử đỏ. */
$tk = file_get_contents(__DIR__.'/../../../wordpress/khh-platform/tai-khoan-cua-toi.php');
eval(preg_replace('/^<\?php/', '', $tk, 1));

/* nạp plugin, bỏ qua phần khai báo trùng */
$src = file_get_contents(__DIR__.'/../../../wordpress/khh-platform/khh-platform.php');
$src = preg_replace('/^<\?php/', '', $src, 1);
$src = preg_replace('/^require_once KHH_DIR .*$/m', '', $src);
foreach (['khh_table','khh_get_coll','khh_put_doc'] as $fn) {
  $src = preg_replace('/\nfunction '.$fn.'\s*\([^)]*\)\s*\{.*?\n\}/s', "\n", $src, 1);
}
eval($src);

$fail = 0;
function t($name,$got,$want){ global $fail;
  $ok = $got === $want; if(!$ok){ $fail++; }
  printf("%s  %-52s got=%s\n", $ok?'PASS':'FAIL', $name, var_export($got,true)); }

/* 1. làm sạch mã bản ghi */
t('id hợp lệ', khh_clean_id('t01'), 't01');
t('id có ký tự lạ bị loại', khh_clean_id('a/../b'), '');
t('id rỗng', khh_clean_id(''), '');

/* 2. ánh xạ vai trò */
t('owner → administrator', khh_wp_role_for('owner'), 'administrator');
t('staff → khh_staff', khh_wp_role_for('staff'), 'khh_staff');
t('vai trò lạ → khh_staff', khh_wp_role_for('hacker'), 'khh_staff');

/* 3. nhóm dữ liệu bị khoá ghi */
t('staff nằm trong nhóm hạn chế', in_array('staff', khh_restricted_collections(), true), true);
t('tasks không bị hạn chế', in_array('tasks', khh_restricted_collections(), true), false);

/* 4. đăng nhập Google */
update_option('khh_settings', ['google_client_id'=>'CID','allowed_domains'=>'khh.vn','only_staff'=>1,'default_role'=>'staff']);
$GLOBALS['_docs']['staff']['s1'] = ['name'=>'Quang Thắng','email'=>'thang@khh.vn','role'=>'admin','title'=>'Quản lý dự án'];

$claims_ok = ['email'=>'thang@khh.vn','aud'=>'CID','email_verified'=>'true','exp'=>time()+600,'name'=>'Quang Thang'];
$u = khh_login_or_provision($claims_ok);
t('tạo tài khoản cho nhân sự có hồ sơ', is_wp_error($u)?$u->get_error_message():$u->user_login, 'thang');
t('tên hiển thị lấy từ hồ sơ', $GLOBALS['_users']['thang@khh.vn']->display_name, 'Quang Thắng');
t('vai trò WordPress theo hồ sơ', $GLOBALS['_users']['thang@khh.vn']->roles[0], 'khh_admin');
t('gắn đúng mã hồ sơ', get_user_meta(10,'khh_staff_id'), 's1');

$u2 = khh_login_or_provision($claims_ok);
t('lần sau dùng lại tài khoản cũ', $u2->ID, 10);

$bad_domain = ['email'=>'ai@gmail.com','aud'=>'CID','email_verified'=>'true','exp'=>time()+600];
$r = khh_login_or_provision($bad_domain);
t('chặn email ngoài tên miền', is_wp_error($r) && $r->c==='khh_domain', true);

$no_staff = ['email'=>'nguoila@khh.vn','aud'=>'CID','email_verified'=>'true','exp'=>time()+600];
$r2 = khh_login_or_provision($no_staff);
t('chặn email chưa có hồ sơ khi bật only_staff', is_wp_error($r2) && $r2->c==='khh_no_staff', true);

update_option('khh_settings', ['google_client_id'=>'CID','allowed_domains'=>'khh.vn','only_staff'=>0,'default_role'=>'manager']);
$r3 = khh_login_or_provision($no_staff);
t('tắt only_staff thì tạo tài khoản mới', is_wp_error($r3)?'ERR':$r3->roles[0], 'khh_manager');

/* 5. xác minh token */
$GLOBALS['_google_response'] = ['code'=>200,'body'=>json_encode(['email'=>'a@khh.vn','aud'=>'SAI','email_verified'=>'true','exp'=>time()+600])];
$v = khh_verify_google_token('x');
t('từ chối token của ứng dụng khác', is_wp_error($v) && $v->c==='khh_google_aud', true);

$GLOBALS['_google_response'] = ['code'=>200,'body'=>json_encode(['email'=>'a@khh.vn','aud'=>'CID','email_verified'=>'true','exp'=>time()-600])];
$v2 = khh_verify_google_token('x');
t('từ chối token hết hạn', is_wp_error($v2) && $v2->c==='khh_google_exp', true);

$GLOBALS['_google_response'] = ['code'=>400,'body'=>'{}'];
$v3 = khh_verify_google_token('x');
t('từ chối khi Google trả lỗi', is_wp_error($v3) && $v3->c==='khh_google_bad', true);

$GLOBALS['_google_response'] = ['code'=>200,'body'=>json_encode(['email'=>'a@khh.vn','aud'=>'CID','email_verified'=>'true','exp'=>time()+600])];
$v4 = khh_verify_google_token('x');
t('chấp nhận token hợp lệ', is_array($v4) && $v4['email']==='a@khh.vn', true);

/* 6. đường dẫn Thoát đi thẳng vào JavaScript nên KHÔNG được bọc dấu & */
$cfg = khh_api_config();
t('cấu hình có đường dẫn thoát', !empty($cfg['out']), true);
t('  không bọc & thành &#038;', strpos($cfg['out'], '&#038;') === false, true);
t('  không bọc & thành &amp;', strpos($cfg['out'], '&amp;') === false, true);
parse_str(parse_url($cfg['out'], PHP_URL_QUERY) ?: '', $q);
t('  đọc ra đúng tham số khh_thoat', isset($q['khh_thoat']), true);
t('  đọc ra đúng tham số _wpnonce', isset($q['_wpnonce']), true);
t('  KHÔNG sinh ra tham số hỏng "amp;_wpnonce"', isset($q['amp;_wpnonce']), false);
t('  mã chống giả mạo đúng việc khh_thoat', $q['_wpnonce'], wp_create_nonce('khh_thoat'));
t('cấu hình có tên đăng nhập cho hộp Tài khoản', $cfg['login'], 'mnnv2kvc0008');

/* cho thấy vì sao không dùng wp_nonce_url: nó bọc & nên tham số bị hỏng */
parse_str(parse_url(wp_nonce_url('https://site.test/?khh_thoat=1','khh_thoat'), PHP_URL_QUERY) ?: '', $q2);
t('(đối chứng) wp_nonce_url dùng ở đây sẽ sinh tham số hỏng', isset($q2['amp;_wpnonce']), true);

/* 7. cấu hình nói rõ giới hạn tải tệp để giao diện báo trước */
t('cấu hình có mức tải tệp tối đa', $cfg['maxUpload'], 2*1024*1024);
t('cấu hình nói tài khoản có quyền tải tệp', $cfg['canUpload'], 1);

/* 8. vai trò nền tảng phải có quyền tải tệp — kể cả ở bản cài từ trước */
khh_register_roles();
t('vai trò nhân viên được tạo', get_role('khh_staff') !== null, true);
t('  có quyền tải tệp', get_role('khh_staff')->has_cap('upload_files'), true);
t('  có quyền đọc', get_role('khh_staff')->has_cap('read'), true);
t('  KHÔNG có quyền sửa website', get_role('khh_staff')->has_cap('edit_posts'), false);

/* giả lập bản cũ: vai trò đã có sẵn nhưng thiếu quyền tải tệp */
$GLOBALS['_roles'] = ['khh_staff' => new WP_Role('khh_staff', ['read'=>true])];
t('(bản cũ) thiếu quyền tải tệp', get_role('khh_staff')->has_cap('upload_files'), false);
khh_register_roles();
t('chạy lại thì vá được vai trò cũ', get_role('khh_staff')->has_cap('upload_files'), true);
t('  quyền cũ không bị mất', get_role('khh_staff')->has_cap('read'), true);
t('  hai vai trò còn lại cũng được tạo',
  get_role('khh_manager') !== null && get_role('khh_admin') !== null, true);

/* 9. nâng cấp tự chạy khi số hiệu bản khác với bản đã ghi */
$GLOBALS['_roles'] = ['khh_staff' => new WP_Role('khh_staff', ['read'=>true])];
update_option('khh_version', '1.0.0');
khh_nang_cap();
t('đổi bản thì tự cấp lại quyền', get_role('khh_staff')->has_cap('upload_files'), true);
t('  ghi lại số hiệu bản mới', get_option('khh_version'), KHH_VERSION);
$GLOBALS['_roles'] = ['khh_staff' => new WP_Role('khh_staff', ['read'=>true])];
khh_nang_cap();
t('cùng bản thì không đụng gì nữa', get_role('khh_staff')->has_cap('upload_files'), false);

/* 10. bảng tự chẩn đoán nói đúng bệnh */
khh_register_roles();
ob_start(); khh_chan_doan_tep(); $hop = ob_get_clean();
t('máy chủ ổn thì báo sẵn sàng', strpos($hop, 'sẵn sàng') !== false, true);
t('  hiện mức dung lượng thật', strpos($hop, '2 MB') !== false, true);

$GLOBALS['_ghiduoc'] = false;
ob_start(); khh_chan_doan_tep(); $hop2 = ob_get_clean();
t('kho tệp không ghi được thì báo vướng', strpos($hop2, 'còn vướng') !== false, true);
t('  chỉ đúng thư mục đang kẹt', strpos($hop2, '/var/www/wp-content/uploads') !== false, true);
$GLOBALS['_ghiduoc'] = true;

$GLOBALS['_roles'] = [];
ob_start(); khh_chan_doan_tep(); $hop3 = ob_get_clean();
t('vai trò thiếu quyền thì bị nêu tên', strpos($hop3, 'Nhân viên K&amp;H') !== false, true);
t('  bày nút cấp quyền ngay tại chỗ', strpos($hop3, 'khh_cap_quyen_tep') !== false, true);
t('  không đổ lỗi cho host khi host không sai', strpos($hop3, 'Sửa ở phía host') !== false, false);
khh_register_roles();
ob_start(); khh_chan_doan_tep(); $hop4 = ob_get_clean();
t('cấp đủ rồi thì giấu nút đi', strpos($hop4, 'khh_cap_quyen_tep') !== false, false);

echo $fail ? "\n$fail phép thử KHÔNG đạt\n" : "\nTất cả phép thử đều đạt\n";
exit($fail ? 1 : 0);

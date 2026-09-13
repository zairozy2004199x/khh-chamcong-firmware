<?php
/**
 * Khung trang riêng cho hai trang bán vé: không mượn header/footer của theme,
 * nội dung giãn hết bề ngang màn hình. Tắt được trong Cài đặt.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>html,body{margin:0;padding:0}</style>
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'dvr-toan-man' ); ?>>
<?php wp_body_open(); ?>

<div class="dvr">
	<header class="dvr-top">
		<a class="dvr-home" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
		<nav class="dvr-nav">
			<?php
			$t = DVR_Admin::trang_khach();
			if ( $t['bang_gia'] ) {
				echo '<a href="' . esc_url( $t['bang_gia'] ) . '">Bảng giá</a>';
			}
			if ( $t['dat_ve'] ) {
				echo '<a href="' . esc_url( $t['dat_ve'] ) . '">Đặt vé</a>';
			}
			?>
		</nav>
	</header>
</div>

<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
?>

<div class="dvr">
	<footer class="dvr-day">
		<span><?php echo esc_html( get_bloginfo( 'name' ) ); ?> · <?php echo esc_html( gmdate( 'Y' ) ); ?></span>
		<span>Giá và tình trạng chỗ do hãng quyết định tại thời điểm xuất vé.</span>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>

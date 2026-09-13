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
		<?php
		$dvr_logo = dvr_cai_dat( 'logo_url', '' );
		if ( $dvr_logo ) {
			echo '<a class="logo" href="' . esc_url( home_url( '/' ) ) . '"><img src="' . esc_url( $dvr_logo )
				. '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"></a>';
		} else {
			?>
			<a class="logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<span class="logo-dau" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M2.4 11.2 20.9 3.1c.7-.3 1.4.4 1.1 1.1l-8.1 18.5c-.3.7-1.3.6-1.5-.1l-2.1-6.5a1 1 0 0 0-.6-.6L3.2 13.4c-.7-.2-.8-1.2-.8-1.5Z" fill="#fff"/>
					<path d="M10.3 15.5 20.8 4.3" stroke="#FFD7B0" stroke-width="1.3" stroke-linecap="round"/>
				</svg></span>
				<span class="logo-chu"><b><?php echo esc_html( dvr_cai_dat( 'hero_ten', 'Dò Vé Rẻ' ) ); ?></b><small>Vé máy bay trực tuyến</small></span>
			</a>
			<?php
		}
		?>
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

<div class="dvr dvr-ngoai">
<?php
while ( have_posts() ) {
	the_post();
	the_content();
}
?>
</div>

<div class="dvr">
	<footer class="dvr-day">
		<span>Giá và tình trạng chỗ do hãng quyết định tại thời điểm xuất vé.</span>
	</footer>
</div>

<?php DVR_Shortcodes::chan_trang( true ); ?>

<?php wp_footer(); ?>
</body>
</html>

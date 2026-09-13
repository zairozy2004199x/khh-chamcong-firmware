<?php
/** Trang Cài đặt của plugin. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cd = dvr_cai_dat();
?>
<div class="wrap">
	<h1>Cài đặt Dò Vé Rẻ</h1>

	<div class="notice notice-info inline" style="margin:14px 0;padding:10px 14px">
		<p><b>Hai shortcode:</b></p>
		<p><code>[do_ve_re]</code> — trang bảng giá cho khách dò chuyến.<br>
		<code>[do_ve_re_dat_ve]</code> — trang khách điền thông tin và chuyển khoản.</p>
		<p>Tạo hai trang, dán mỗi shortcode vào một trang, rồi chọn trang đặt vé ở ô bên dưới.</p>
		<p><b>Webhook ngân hàng:</b> <code><?php echo esc_html( rest_url( 'dovere/v1/webhook/bank' ) ); ?></code>
		— khai ở SePay/Casso, gửi kèm header <code>Authorization: Apikey &lt;khoá&gt;</code>.</p>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields( 'dovere' ); ?>

		<h2 class="title">Nhận tiền của khách</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dvr_bank_id">Mã ngân hàng (BIN)</label></th>
				<td><input name="dovere_settings[bank_id]" id="dvr_bank_id" class="regular-text" value="<?php echo esc_attr( $cd['bank_id'] ); ?>" placeholder="970436">
					<p class="description">Mã VietQR của ngân hàng, ví dụ Vietcombank là 970436. Tra ở vietqr.io/danh-sach-api.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_bank_account">Số tài khoản</label></th>
				<td><input name="dovere_settings[bank_account]" id="dvr_bank_account" class="regular-text" value="<?php echo esc_attr( $cd['bank_account'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_bank_name">Chủ tài khoản</label></th>
				<td><input name="dovere_settings[bank_name]" id="dvr_bank_name" class="regular-text" value="<?php echo esc_attr( $cd['bank_name'] ); ?>" placeholder="CONG TY TNHH ..."></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_bank_label">Tên ngân hàng hiện cho khách</label></th>
				<td><input name="dovere_settings[bank_label]" id="dvr_bank_label" class="regular-text" value="<?php echo esc_attr( $cd['bank_label'] ); ?>" placeholder="Vietcombank"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_webhook">Khoá webhook</label></th>
				<td><input name="dovere_settings[webhook_secret]" id="dvr_webhook" class="regular-text" value="<?php echo esc_attr( $cd['webhook_secret'] ); ?>">
					<p class="description">Khoá của dịch vụ báo biến động số dư. Để trống thì tiền về phải tự bấm “Đã nhận tiền”.</p></td>
			</tr>
		</table>

		<h2 class="title">Giá bán và giữ giá</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dvr_fee_pct">Phí dịch vụ (%)</label></th>
				<td><input type="number" step="0.1" min="0" name="dovere_settings[fee_pct]" id="dvr_fee_pct" value="<?php echo esc_attr( $cd['fee_pct'] ); ?>" class="small-text">
					&nbsp; cộng thêm <input type="number" min="0" name="dovere_settings[fee_flat]" value="<?php echo esc_attr( $cd['fee_flat'] ); ?>" class="small-text">đ
					&nbsp; tối thiểu <input type="number" min="0" name="dovere_settings[fee_min]" value="<?php echo esc_attr( $cd['fee_min'] ); ?>" class="small-text">đ
					<p class="description">Phần chênh lệch của mình, hiện rõ cho khách thấy trước khi trả tiền.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_hold">Giữ giá (phút)</label></th>
				<td><input type="number" min="5" name="dovere_settings[hold_minutes]" id="dvr_hold" value="<?php echo esc_attr( $cd['hold_minutes'] ); ?>" class="small-text">
					<p class="description">Quá hạn mà khách chưa chuyển khoản thì đơn thành “quá hạn giữ giá”.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_order_page">Trang đặt vé</label></th>
				<td><?php
					wp_dropdown_pages( array(
						'name'              => 'dovere_settings[order_page]',
						'id'                => 'dvr_order_page',
						'selected'          => (int) $cd['order_page'],
						'show_option_none'  => '— chọn trang có shortcode [do_ve_re_dat_ve] —',
						'option_none_value' => 0,
					) );
					?>
					<p class="description">Chọn xong thì bảng giá mới hiện nút <b>Chọn</b> dẫn khách sang đây.</p></td>
			</tr>
		</table>

		<h2 class="title">Giá thật từ Amadeus</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="dvr_am_id">API Key</label></th>
				<td><input name="dovere_settings[amadeus_id]" id="dvr_am_id" class="regular-text" value="<?php echo esc_attr( $cd['amadeus_id'] ); ?>" autocomplete="off">
					<p class="description">Lấy ở developers.amadeus.com. Để trống thì bảng giá chạy bằng giá mô phỏng.</p></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_am_secret">API Secret</label></th>
				<td><input type="password" name="dovere_settings[amadeus_secret]" id="dvr_am_secret" class="regular-text" value="<?php echo esc_attr( $cd['amadeus_secret'] ); ?>" autocomplete="off"></td>
			</tr>
			<tr>
				<th scope="row">Môi trường</th>
				<td>
					<label><input type="radio" name="dovere_settings[amadeus_env]" value="test" <?php checked( 'test', $cd['amadeus_env'] ); ?>> Thử nghiệm (dữ liệu sandbox)</label><br>
					<label><input type="radio" name="dovere_settings[amadeus_env]" value="production" <?php checked( 'production', $cd['amadeus_env'] ); ?>> Thật (có tính phí theo lượt gọi)</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_tax">Dịch vụ tra mã số thuế</label></th>
				<td><input name="dovere_settings[tax_api]" id="dvr_tax" class="regular-text code" value="<?php echo esc_attr( $cd['tax_api'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="dvr_shop">Tên hiển thị trong email</label></th>
				<td><input name="dovere_settings[shop_name]" id="dvr_shop" class="regular-text" value="<?php echo esc_attr( $cd['shop_name'] ); ?>"></td>
			</tr>
		</table>

		<?php submit_button( 'Lưu cài đặt' ); ?>
	</form>
</div>

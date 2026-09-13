<?php
/** Bốn mẫu thư gửi khách, gửi bằng wp_mail nên dùng luôn cấu hình SMTP của WordPress. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DVR_Mail {

	public static function ngay_vn( $s ) {
		$p = explode( '-', (string) $s );
		return count( $p ) === 3 ? $p[2] . '/' . $p[1] . '/' . $p[0] : (string) $s;
	}

	private static function chuyen( $o ) {
		$r = explode( '-', $o['flight']['route'] );
		return $o['flight']['airline'] . ' ' . $o['flight']['number'] . ' · ' . $r[0] . ' ' . $o['flight']['dep']
			. ' → ' . ( isset( $r[1] ) ? $r[1] : '' ) . ' ' . $o['flight']['arr'] . ' · ' . self::ngay_vn( $o['flight']['date'] );
	}

	private static function bang( $hang ) {
		$h = '';
		foreach ( $hang as $d ) {
			$h .= '<tr><td style="padding:5px 0;color:#6B839C;width:42%">' . esc_html( $d[0] ) . '</td>'
				. '<td style="padding:5px 0;text-align:right;font-weight:600">' . $d[1] . '</td></tr>';
		}
		return '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px">' . $h . '</table>';
	}

	private static function khung( $a ) {
		$mau  = isset( $a['mau'] ) ? $a['mau'] : '#0B2440';
		$shop = esc_html( $a['shop'] );
		$nut  = ! empty( $a['link'] )
			? '<tr><td style="padding:0 22px 22px"><a href="' . esc_url( $a['link'] ) . '" style="display:inline-block;background:#E8890B;color:#1B1000;text-decoration:none;font-weight:700;padding:11px 18px">Xem đơn của tôi</a></td></tr>'
			: '';
		$dam  = ! empty( $a['dam'] )
			? '<tr><td style="padding:18px 22px 0"><div style="border-left:4px solid #E8890B;background:#FBF3E8;padding:12px 14px;font-size:15px">' . $a['dam'] . '</div></td></tr>'
			: '';
		return '<div style="margin:0;padding:24px 12px;background:#EEF1F4;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#0B2440">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #CCD6E1">'
			. '<tr><td style="background:' . $mau . ';color:#E9F0F8;padding:18px 22px">'
			. '<div style="font-size:11px;letter-spacing:.16em;text-transform:uppercase;opacity:.75">' . $shop . '</div>'
			. '<div style="font-size:22px;font-weight:700;margin-top:4px">' . esc_html( $a['tieu'] ) . '</div></td></tr>'
			. $dam
			. '<tr><td style="padding:18px 22px;font-size:14px;line-height:1.6">' . $a['than'] . '</td></tr>'
			. $nut
			. '<tr><td style="padding:14px 22px;border-top:1px solid #E1E7EE;font-size:12px;color:#6B839C">'
			. 'Thư tự động từ ' . $shop . '. Cần đổi gì thì trả lời thẳng thư này.</td></tr></table></div>';
	}

	/** Trả về array( 'subject' => …, 'html' => … ) cho một mốc của đơn. */
	public static function soan( $kind, $o, $cd = null ) {
		$cd   = $cd ? $cd : dvr_cai_dat();
		$shop = $cd['shop_name'];
		$link = ! empty( $cd['order_page'] ) ? add_query_arg( 'don', $o['code'], get_permalink( $cd['order_page'] ) ) : '';
		$ten  = array();
		foreach ( (array) $o['pax'] as $p ) {
			$ten[] = $p['full'];
		}
		$chung = array(
			array( 'Mã đơn', esc_html( $o['code'] ) ),
			array( 'Chuyến bay', esc_html( self::chuyen( $o ) ) ),
			array( 'Hành khách', esc_html( implode( ', ', $ten ) ) ),
		);

		if ( 'moi' === $kind ) {
			return array(
				'subject' => 'Đơn ' . $o['code'] . ' — chuyển khoản để giữ chỗ',
				'html'    => self::khung( array(
					'shop' => $shop, 'link' => $link, 'tieu' => 'Chuyển khoản để giữ chỗ',
					'dam'  => 'Nội dung chuyển khoản phải giữ nguyên mã <b style="font-family:monospace">' . esc_html( $o['code'] ) . '</b> — hệ thống dò mã đó để khớp tiền về.',
					'than' => self::bang( array_merge( $chung, array(
						array( 'Tiền vé', dvr_tien( $o['money']['fare'] ) ),
						array( 'Phí dịch vụ', dvr_tien( $o['money']['fee'] ) ),
						array( 'Tổng phải chuyển', '<span style="color:#B45F05;font-size:18px">' . dvr_tien( $o['money']['total'] ) . '</span>' ),
						array( 'Ngân hàng', esc_html( $cd['bank_label'] ? $cd['bank_label'] : $cd['bank_id'] ) ),
						array( 'Số tài khoản', esc_html( $cd['bank_account'] ) ),
						array( 'Chủ tài khoản', esc_html( $cd['bank_name'] ) ),
						array( 'Giữ giá tới', esc_html( self::gio( $o['expiresAt'] ) ) ),
					) ) ),
				) ),
			);
		}

		if ( 'da_nhan_tien' === $kind ) {
			$thieu = (int) $o['money']['total'] - (int) $o['money']['paid'];
			return array(
				'subject' => 'Đã nhận tiền đơn ' . $o['code'] . ' — đang mua vé',
				'html'    => self::khung( array(
					'shop' => $shop, 'link' => $link, 'tieu' => 'Đã nhận tiền', 'mau' => '#0B6B54',
					'dam'  => $thieu > 0
						? 'Còn thiếu <b>' . dvr_tien( $thieu ) . '</b> so với tổng đơn — nhờ anh/chị chuyển bổ sung để chúng tôi mua vé.'
						: 'Chúng tôi đang mua vé. Mã đặt chỗ sẽ gửi ngay khi xuất xong.',
					'than' => self::bang( array_merge( $chung, array(
						array( 'Đã nhận', dvr_tien( $o['money']['paid'] ) ),
						array( 'Tổng đơn', dvr_tien( $o['money']['total'] ) ),
					) ) ),
				) ),
			);
		}

		if ( 'da_xuat_ve' === $kind ) {
			return array(
				'subject' => 'Vé đã xuất — mã đặt chỗ ' . $o['pnr'] . ' (đơn ' . $o['code'] . ')',
				'html'    => self::khung( array(
					'shop' => $shop, 'link' => $link, 'tieu' => 'Vé đã xuất', 'mau' => '#0B6B54',
					'dam'  => 'Mã đặt chỗ: <b style="font-family:monospace;font-size:20px;letter-spacing:.06em">' . esc_html( $o['pnr'] ) . '</b>',
					'than' => self::bang( array_merge( $chung, array(
						array( 'Mã đặt chỗ', '<span style="font-family:monospace">' . esc_html( $o['pnr'] ) . '</span>' ),
					) ) )
						. '<p style="margin:14px 0 0;color:#39536E">Làm thủ tục bằng mã đặt chỗ trên web hoặc app của hãng, hoặc tại quầy. '
						. 'Mang giấy tờ tuỳ thân trùng tên đã đặt, có mặt ở sân bay trước giờ bay ít nhất 2 tiếng.</p>',
				) ),
			);
		}

		if ( 'hoan_tien' === $kind ) {
			return array(
				'subject' => 'Hoàn tiền đơn ' . $o['code'],
				'html'    => self::khung( array(
					'shop' => $shop, 'link' => $link, 'tieu' => 'Đã hoàn tiền', 'mau' => '#8C3310',
					'dam'  => 'Hoàn lại <b>' . dvr_tien( $o['money']['paid'] ) . '</b> về tài khoản anh/chị đã chuyển.',
					'than' => self::bang( array_merge( $chung, array( array( 'Số tiền hoàn', dvr_tien( $o['money']['paid'] ) ) ) ) )
						. '<p style="margin:14px 0 0;color:#39536E">Tiền thường về trong ngày làm việc. Rất xin lỗi vì chuyến này không giữ được chỗ.</p>',
				) ),
			);
		}

		return null;
	}

	private static function gio( $s ) {
		return date_i18n( 'H:i d/m/Y', strtotime( $s ) );
	}

	/** Gửi thư và ghi kết quả vào đơn. */
	public static function gui( $code, $kind ) {
		$o = DVR_Store::lay( $code );
		if ( ! $o ) {
			return false;
		}
		$to = isset( $o['contact']['email'] ) ? $o['contact']['email'] : '';
		if ( ! $to ) {
			return false;
		}
		$thu = self::soan( $kind, $o );
		if ( ! $thu ) {
			return false;
		}
		$loc = function () {
			return 'text/html';
		};
		add_filter( 'wp_mail_content_type', $loc );
		$ok = wp_mail( $to, $thu['subject'], $thu['html'] );
		remove_filter( 'wp_mail_content_type', $loc );

		$data          = array( 'mail' => isset( $o['mail'] ) ? $o['mail'] : array() );
		$data['mail'][] = array( 'at' => current_time( 'mysql' ), 'kind' => $kind, 'to' => $to, 'ok' => (bool) $ok );
		DVR_Store::sua(
			$code,
			array( 'data' => $data ),
			$ok ? 'Đã gửi thư "' . $kind . '" tới ' . $to : 'Gửi thư "' . $kind . '" HỎNG (xem cấu hình SMTP của WordPress)'
		);
		return (bool) $ok;
	}
}

<?php
/**
 * VCG_Nguoi — ai đang dùng, và vai gì.
 *
 * Hai nguồn danh tính, xét theo thứ tự:
 *   1. Đăng nhập WordPress có quyền quản trị -> ADMIN.
 *   2. Phiên PIN của plugin chấm công cũ (`VHCC_Auth`) nếu nó đang bật.
 *
 * Giữ cả hai vì bản mới chạy SONG SONG với bản cũ. Anh Thắng vào bằng tài khoản WordPress;
 * nhân viên vào bằng PIN. Ép mọi người về một đường trong lúc chuyển giao là chặn mất một nhóm.
 *
 * @package vhcp-cong
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class VCG_Nguoi {

	/** @return array{vai:string,ten:string,co_so:array} */
	public static function hien_tai() {
		/* Quản trị WordPress luôn là ADMIN. Đây là đường anh Thắng dùng, và cũng là đường an
		   toàn nhất cho việc nạp — nó dựa vào phiên đăng nhập thật của WordPress chứ không phải
		   một mã PIN nằm trong bảng dữ liệu. */
		if ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ) {
			$u = wp_get_current_user();
			return array(
				'vai'   => VCG_Quyen::ADMIN,
				'ten'   => $u && $u->display_name ? $u->display_name : 'Quản trị',
				'co_so' => array(),
			);
		}

		/* Phiên PIN của hệ chấm công, nếu có. Dùng lại thay vì làm phiên mới: hai hệ đăng nhập
		   song song là hai chỗ để rò, và người dùng phải nhớ hai thứ.
		 *
		 * 🔴 SỬA 26/08/2026: nhánh này gọi `VHCC_Auth::phien()` — một hàm KHÔNG HỀ TỒN TẠI. Có
		 *    `method_exists` gác nên không trắng trang, nhưng nhánh chưa từng chạy lấy một lần:
		 *    mọi người vào bằng PIN đều rơi xuống `vai => ''` và không thấy gì, mà chẳng có lỗi
		 *    nào phát ra. `tools/test/kiem-tham-chieu.php` bắt được ngay khi nó thôi gõ tay danh
		 *    sách plugin và dò cả thư mục `wordpress/`.
		 *
		 * ⚠️ `user_by_token()` trả khoá `name` / `role` / `coso` (một chuỗi), KHÔNG phải
		 *    `ho_ten` / `vai_tro` / `cua_hang` như bản gọi hụt ở trên tưởng. */
		if ( class_exists( 'VHCC_Web' ) && defined( 'VHCC_Web::COOKIE' )
			&& method_exists( 'VHCC_Auth', 'user_by_token' ) ) {
			$c = constant( 'VHCC_Web::COOKIE' );
			if ( $c && ! empty( $_COOKIE[ $c ] ) ) {
				$p = VHCC_Auth::user_by_token( (string) $_COOKIE[ $c ] );
				if ( is_array( $p ) && ! empty( $p['role'] ) ) {
					$cs = trim( (string) ( isset( $p['coso'] ) ? $p['coso'] : '' ) );
					return array(
						'vai'   => VCG_Quyen::chuan( $p['role'] ),
						'ten'   => isset( $p['name'] ) ? (string) $p['name'] : '',
						'co_so' => ( '' !== $cs ) ? array( $cs ) : array(),
					);
				}
			}
		}

		return array( 'vai' => '', 'ten' => '', 'co_so' => array() );
	}
}

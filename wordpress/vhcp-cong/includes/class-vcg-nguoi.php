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

		/* Phiên PIN của bản cũ, nếu có. Dùng lại thay vì làm phiên mới: hai hệ đăng nhập song
		   song là hai chỗ để rò, và người dùng phải nhớ hai thứ. */
		if ( class_exists( 'VHCC_Auth' ) && method_exists( 'VHCC_Auth', 'phien' ) ) {
			$p = VHCC_Auth::phien();
			if ( is_array( $p ) && ! empty( $p['vai_tro'] ) ) {
				return array(
					'vai'   => VCG_Quyen::chuan( $p['vai_tro'] ),
					'ten'   => isset( $p['ho_ten'] ) ? (string) $p['ho_ten'] : '',
					'co_so' => isset( $p['cua_hang'] ) ? (array) $p['cua_hang'] : array(),
				);
			}
		}

		return array( 'vai' => '', 'ten' => '', 'co_so' => array() );
	}
}

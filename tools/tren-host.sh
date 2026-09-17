#!/usr/bin/env bash
# ══════════════════════════════════════════════════════════════════════════════════════════════
# MỘT LỆNH CHẠY TRÊN HOSTING — soát, khai khoá, kiểm bản mới
#
# Anh Thắng 13/09/2026: *"làm chung 1 lệnh để anh gửi các nơi"*.
#
# Gộp việc của hai script cũ (`soat-plugin-tren-host.sh`, `khai-khoa-github.sh`) làm một, để
# gửi đi các cơ sở thì chỉ còn MỘT dòng dán vào là xong — người nhận không phải chọn chạy cái
# nào trước.
#
# ──────────────────────────────────────────────────────────────────────────────────────────────
# CÁCH DÙNG
#
#   ssh <tài khoản>@<host>
#   cd <thư mục có wp-config.php>
#   bash tren-host.sh                # làm cả ba việc, theo thứ tự
#
#   bash tren-host.sh soat           # chỉ xem đang có gì, KHÔNG ghi gì cả
#   bash tren-host.sh khoa tatca     # MỘT lượt gõ, khai khoá cho CẢ MƯỜI BỐN bộ  ← nên dùng
#   bash tren-host.sh khoa           # chỉ khai khoá GitHub
#   bash tren-host.sh khoa vhcphn_gh_token    # khoá của BẢN VÙNG Hà Nội (site riêng)
#   bash tren-host.sh capnhat        # chỉ bắt WordPress hỏi lại GitHub ngay
#
# ══════════════════════════════════════════════════════════════════════════════════════════════
# 🔴 KHÔNG BAO GIỜ ĐƯA KHOÁ VÀO DÒNG LỆNH.
#    `bash tren-host.sh khoa github_pat_xxx` trông tiện hơn, nhưng chuỗi ấy nằm lại trong
#    `~/.bash_history` của máy chủ và hiện ra với bất kỳ ai gõ `ps` đúng lúc lệnh đang chạy.
#    Script đọc từ bàn phím bằng `read -s` — gõ xong không hiện, không lưu đâu cả. Tham số thứ
#    hai của `khoa` là TÊN Ô, không phải khoá.
#
# 🔴 SOÁT KHÔNG GHI GÌ. Gửi cho người lạ máy thì việc đầu tiên họ chạy phải là việc không làm
#    hỏng được gì. `soat` chỉ đọc.
# ══════════════════════════════════════════════════════════════════════════════════════════════
set -uo pipefail

VIEC="${1:-tatca}"
O_KHOA="${2:-vhcp_gh_token}"

# ⚠️ THÊM MỘT BỘ THÌ THÊM VÀO CẢ HAI DÒNG DƯỚI. Quên là bộ ấy không hiện lúc soát và không
#    bao giờ được nhắc hỏi lại GitHub — nó đứng yên mãi ở bản cũ mà không có dòng lỗi nào.
#    `tools/test/kiem-tren-host-du-bo.php` nay canh đúng chuyện này: bộ nào có lớp tự cập nhật
#    mà thiếu ở đây là bộ thử đỏ.
#
# Anh Thắng 14/09/2026 tách chi phí theo mảng, nên có bốn bản chi phí: KVC (bản gốc) · MTD ·
# VP · TỔNG. Bốn bản ấy cài chung một WordPress nên mỗi bản phải có Ô cấu hình riêng, không
# thì đổi bên này đổi luôn bên kia.
DS_PLUGIN="vhcp-chi-phi vhcp-chi-phi-mtd vhcp-chi-phi-vp vhcp-chi-phi-tong vhcp-cham-cong vhcp-ghe vhcp-noi-bo vhcp-trang-chu vhcp-hop-dong vhcp-du-an vhcp-chi-phi-hn khh-platform khh-doanh-thu vhcp-cc-app"
DS_NHO="vhcp vhcpmtd vhcpvp vhcpt vhcc vhg vhnb vhtc vhd vhda vhcphn khh khhdt ccapp"

# ══════════════════════════════════════════════════════════════════════════════════════════════
# Ô KHOÁ GITHUB — CHỈ NĂM Ô, KHÔNG PHẢI MỖI BỘ MỘT Ô.
#
# 🔴 17/09/2026: dòng này trước đây liệt kê mười một ô, một ô cho mỗi bộ trong DS_PLUGIN —
#    `vhcc_gh_token`, `vhg_gh_token`, `vhnb_gh_token`, `vhtc_gh_token`, `vhd_gh_token`,
#    `vhda_gh_token`. SÁU Ô ẤY KHÔNG BỘ NÀO ĐỌC. Mở bất kỳ lớp tự cập nhật nào ra xem
#    `const O_KHOA` là thấy: chấm công, ghế, nội bộ, trang chủ, hợp đồng, dự án, nền tảng,
#    doanh thu và chi phí KVC đều đọc chung `vhcp_gh_token`.
#
#    Khai khoá vẫn chạy được, vì `vhcp_gh_token` có trong danh sách — nên lỗi này không làm
#    hỏng việc gì, chỉ ghi thừa sáu Option chết. Chỗ nó thật sự đánh lừa là lượt SOÁT: sáu ô
#    ấy báo "ĐÃ KHAI" và người soát yên tâm, trong khi con số ấy không nói gì về việc bộ kia
#    có khoá hay không.
#
# ⚠️ Danh sách này KHÔNG song song với DS_PLUGIN — nhiều bộ dùng chung một ô. Lấy đúng tập
#    `O_KHOA` có thật trong mã, `kiem-tren-host-du-bo.php` đối chiếu giúp.
# ══════════════════════════════════════════════════════════════════════════════════════════════
DS_O_KHOA="vhcp_gh_token vhcpmtd_gh_token vhcpvp_gh_token vhcpt_gh_token vhcphn_gh_token"

gach() { printf '─%.0s' $(seq 1 72); echo; }

# ── Chốt môi trường ──────────────────────────────────────────────────────────────────────────
if ! command -v wp >/dev/null 2>&1; then
  echo "🔴 Không thấy lệnh 'wp' (WP-CLI) trên máy này."
  echo
  echo "   Cách thủ công thay thế:"
  echo "     · xem plugin đang có : ls -1 wp-content/plugins/"
  echo "     · khai khoá GitHub   : wp-admin → Vận Hành Chi Phí → Cài đặt → ô 'Khoá GitHub'"
  exit 1
fi
if ! wp core is-installed >/dev/null 2>&1; then
  echo "🔴 Thư mục hiện tại không phải gốc WordPress."
  echo "   cd tới thư mục có wp-config.php rồi chạy lại. Đang đứng ở: $(pwd)"
  exit 1
fi

# ══ VIỆC 1: SOÁT ═════════════════════════════════════════════════════════════════════════════
viec_soat() {
  gach
  echo "1. ĐANG CÓ GÌ TRÊN TRANG NÀY   ·   $(date '+%d/%m/%Y %H:%M')"
  echo "   $(wp option get siteurl 2>/dev/null || echo '(không đọc được địa chỉ)')"
  gach

  echo
  echo "▸ Plugin của K&H đang cài:"
  local co=0
  for p in $DS_PLUGIN; do
    if wp plugin is-installed "$p" >/dev/null 2>&1; then
      co=1
      printf '    %-20s %-10s %s\n' "$p" \
        "$(wp plugin get "$p" --field=status 2>/dev/null)" \
        "$(wp plugin get "$p" --field=version 2>/dev/null)"
    fi
  done
  [ "$co" = "1" ] || echo "    (chưa cài bộ nào của K&H)"

  echo
  echo "▸ Plugin KHÁC trên trang (không phải của K&H):"
  # ⚠️ Đây mới là chỗ trả lời câu "còn nhiều trang chưa thấy trong này": bộ nào đang chạy thật
  #    mà repo không giữ mã thì nó hiện ở đây. Mất máy chủ là mất luôn mã của chúng.
  wp plugin list --field=name 2>/dev/null | while read -r ten; do
    case " $DS_PLUGIN " in *" $ten "*) continue ;; esac
    printf '    %-20s %-10s %s\n' "$ten" \
      "$(wp plugin get "$ten" --field=status 2>/dev/null)" \
      "$(wp plugin get "$ten" --field=version 2>/dev/null)"
  done

  echo
  echo "▸ Khoá GitHub (chỉ nói CÓ hay KHÔNG, không in khoá ra):"
  for o in $DS_O_KHOA; do
    local v; v="$(wp option get "$o" 2>/dev/null || true)"
    if [ -n "$v" ]; then printf '    %-20s ĐÃ KHAI (%s ký tự)\n' "$o" "${#v}"
    else                 printf '    %-20s chưa khai\n' "$o"; fi
  done
  echo
}

# ══ VIỆC 2: KHAI KHOÁ ════════════════════════════════════════════════════════════════════════
viec_khoa() {
  gach
  # Khai cho MỘT ô, hay cho MỌI ô — chỉ khác nhau ở danh sách ô sẽ ghi, luồng hỏi khoá thì
  # dùng chung. Viết hai luồng song song là hai chỗ phải sửa mỗi lần đổi luật soát khoá, và chỗ
  # bị quên là chỗ nhận bừa một chuỗi hỏng.
  local ds_ghi="$O_KHOA"
  local nhieu=0
  if [ "$O_KHOA" = "tatca" ]; then ds_ghi="$DS_O_KHOA"; nhieu=1; fi

  if [ "$nhieu" = 1 ]; then
    echo "2. KHAI KHOÁ GITHUB  →  MỌI BỘ (một lượt gõ, ghi vào tất cả các ô)"
  else
    echo "2. KHAI KHOÁ GITHUB  →  ô '$O_KHOA'"
  fi
  gach
  if [ "$nhieu" = 1 ]; then
    # ⚠️ CÙNG MỘT KHOÁ CHO MỌI Ô LÀ CỐ Ý VÀ AN TOÀN: khoá chỉ đọc, trỏ đúng một kho. Cái phải
    #    riêng là Ô — bốn plugin chi phí cài chung một WordPress, dùng chung Ô cấu hình là đổi
    #    bên này đổi luôn bên kia.
    echo "Sẽ ghi cùng một khoá vào:"
    local _o; for _o in $ds_ghi; do printf '    · %s\n' "$_o"; done
    echo
    echo "Tạo khoá: GitHub → Settings → Developer settings → Fine-grained tokens"
    echo "          Only select repositories → khh-chamcong-firmware"
    echo "          Permissions → Contents → Read-only      ← đúng một mục này"
  else
    local cu; cu="$(wp option get "$O_KHOA" 2>/dev/null || true)"
    if [ -n "$cu" ]; then
      echo "Ô này ĐANG CÓ khoá (${#cu} ký tự). Dán khoá mới để thay, hoặc Enter suông để giữ nguyên."
    else
      echo "Ô này chưa khai."
      echo
      echo "Tạo khoá: GitHub → Settings → Developer settings → Fine-grained tokens"
      echo "          Only select repositories → khh-chamcong-firmware"
      echo "          Permissions → Contents → Read-only      ← đúng một mục này"
    fi
  fi
  echo
  # 🔴 `-s`: gõ vào KHÔNG hiện lên màn hình. Một ảnh chụp lúc này là mất khoá.
  printf 'Dán khoá rồi Enter: '
  read -r -s khoa
  echo
  khoa="$(printf '%s' "$khoa" | tr -d '[:space:]')"

  if [ -z "$khoa" ]; then echo "Bỏ trống — giữ nguyên, không đổi gì."; return 0; fi

  # ⚠️ SOÁT HÌNH DẠNG TRƯỚC KHI GHI. Dán nhầm nửa chuỗi mà vẫn ghi vào thì lần cập nhật sau im
  #    lặng không thấy bản mới — một lỗi không có câu báo nào, rất khó lần.
  case "$khoa" in
    github_pat_*|ghp_*|gho_*) : ;;
    *) echo "🔴 Không giống khoá GitHub (phải bắt đầu 'github_pat_' hoặc 'ghp_'). KHÔNG ghi gì."; return 1 ;;
  esac
  if [ "${#khoa}" -lt 30 ]; then
    echo "🔴 Chuỗi quá ngắn (${#khoa} ký tự) — nhiều khả năng dán thiếu. KHÔNG ghi gì."; return 1
  fi

  # ══════════════════════════════════════════════════════════════════════════════════════════
  # MỘT LƯỢT GÕ, KHAI CHO MỌI BỘ — `khoa tatca`
  # ══════════════════════════════════════════════════════════════════════════════════════════
  # Anh Thắng 14/09/2026: *"kèm token chạy git auto cho anh luôn nhé"*. Nay có mười bốn bộ dùng
  # chung NĂM ô khoá (xem khối dài ở DS_O_KHOA: phần lớn các bộ đọc chung `vhcp_gh_token`, chỉ
  # ba bản chi phí theo mảng và bản vùng Hà Nội là có ô riêng). Bắt gõ năm lượt thì lượt cuối
  # là lượt bị bỏ, và bộ ấy im lặng không bao giờ thấy bản mới — không câu lỗi nào, chỉ là nó
  # đứng yên mãi.
  #
  # ⚠️ `$ds_ghi` đã dựng ở đầu hàm — một ô, hay cả năm.
  local hong=0 xong=0 o moi
  for o in $ds_ghi; do
    wp option update "$o" "$khoa" --quiet
    moi="$(wp option get "$o" 2>/dev/null || true)"
    if [ "${#moi}" = "${#khoa}" ]; then
      xong=$((xong+1)); printf '    ✓ %-22s đã khai (%s ký tự)\n' "$o" "${#moi}"
    else
      hong=$((hong+1)); printf '    🔴 %-22s ghi xong nhưng đọc lại không khớp\n' "$o"
    fi
  done
  echo
  if [ "$hong" -gt 0 ]; then
    echo "🔴 $hong ô KHÔNG ghi được. Khai tay mấy ô ấy ở wp-admin → Cài đặt của từng bộ."; return 1
  fi
  echo "✓ Xong $xong ô."
}

# ══ VIỆC 3: BẮT HỎI LẠI GITHUB NGAY ══════════════════════════════════════════════════════════
viec_capnhat() {
  gach
  echo "3. HỎI LẠI GITHUB NGAY (khỏi chờ hết sáu giờ)"
  gach
  for t in $DS_NHO; do wp transient delete "${t}_gh_ban_moi" >/dev/null 2>&1 || true; done
  wp transient delete update_plugins >/dev/null 2>&1 || true
  wp plugin list --field=name >/dev/null 2>&1 || true

  echo
  echo "▸ Bản mới WordPress nhìn thấy:"
  local ra; ra="$(wp plugin list --update=available --fields=name,version,update_version --format=table 2>/dev/null)"
  if [ -n "$ra" ] && [ "$(echo "$ra" | wc -l)" -gt 1 ]; then
    echo "$ra" | sed 's/^/    /'
    echo
    echo "  Cập nhật: vào wp-admin → Plugin → bấm 'Cập nhật ngay'"
    echo "  Hoặc ngay tại đây:  wp plugin update <tên>"
  else
    echo "    (không có bản nào mới — đang chạy bản mới nhất)"
  fi
  echo
}

# ── Chạy ─────────────────────────────────────────────────────────────────────────────────────
case "$VIEC" in
  soat)    viec_soat ;;
  khoa)    viec_khoa ;;
  capnhat) viec_capnhat ;;
  tatca)   viec_soat; viec_khoa && viec_capnhat ;;
  *) echo "Không hiểu '$VIEC'. Dùng: soat | khoa | capnhat | (bỏ trống = cả ba)"; exit 1 ;;
esac

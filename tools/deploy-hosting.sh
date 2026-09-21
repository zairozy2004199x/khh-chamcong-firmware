#!/usr/bin/env bash
# Đẩy plugin "Vận Hành Chi Phí" lên hosting. CHẠY Ở MÁY CÓ MẠNG VÀO ĐƯỢC HOSTING
# (phiên Claude Code trên web bị chặn ra Internet nên không tự đẩy được).
#
# Cách dùng:
#   1) cp tools/deploy-hosting.env.mau tools/deploy-hosting.env   # rồi điền thông tin
#   2) bash tools/deploy-hosting.sh
#
# 3 cách đẩy (đặt VHCP_METHOD trong file .env):
#   ssh  — rsync qua SSH  (khuyên dùng; hosting có SSH như Hostinger/AZDIGI/Vietnix bản Business)
#   ftp  — lftp mirror    (hosting chỉ có FTP/cPanel)
#   zip  — chỉ đóng gói, tự tải lên bằng wp-admin (không cần thông tin đăng nhập)
#
# File tools/deploy-hosting.env KHÔNG được commit (đã nằm trong .gitignore).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC="$ROOT/wordpress/vhcp-chi-phi"
ENVF="$ROOT/tools/deploy-hosting.env"

bash "$ROOT/tools/build-plugin-zip.sh"

if [ ! -f "$ENVF" ]; then
  echo
  echo "Chưa có $ENVF — chỉ đóng gói xong."
  echo "Tải dist/vhcp-chi-phi.zip lên bằng wp-admin → Plugin → Cài mới → Tải plugin lên."
  exit 0
fi

# shellcheck disable=SC1090
set -a; . "$ENVF"; set +a

METHOD="${VHCP_METHOD:-zip}"
REMOTE_DIR="${VHCP_REMOTE_PLUGINS_DIR:-}"

case "$METHOD" in
  zip)
    echo "VHCP_METHOD=zip → chỉ đóng gói. Tải dist/vhcp-chi-phi.zip lên qua wp-admin."
    ;;

  ssh)
    : "${VHCP_SSH_HOST:?thiếu VHCP_SSH_HOST}"
    : "${REMOTE_DIR:?thiếu VHCP_REMOTE_PLUGINS_DIR (vd /home/USER/public_html/wp-content/plugins)}"
    PORT="${VHCP_SSH_PORT:-22}"
    USER_AT="${VHCP_SSH_USER:+${VHCP_SSH_USER}@}"
    echo "→ rsync tới ${USER_AT}${VHCP_SSH_HOST}:${REMOTE_DIR}/vhcp-chi-phi/"
    rsync -az --delete \
      --exclude '.git' --exclude '.DS_Store' --exclude 'node_modules' \
      -e "ssh -p ${PORT}" \
      "$SRC/" "${USER_AT}${VHCP_SSH_HOST}:${REMOTE_DIR}/vhcp-chi-phi/"
    if [ -n "${VHCP_WP_PATH:-}" ]; then
      echo "→ kích hoạt plugin bằng wp-cli trên hosting"
      ssh -p "$PORT" "${USER_AT}${VHCP_SSH_HOST}" \
        "cd '${VHCP_WP_PATH}' && wp plugin activate vhcp-chi-phi && wp rewrite flush" || \
        echo "  (hosting không có wp-cli — vào wp-admin kích hoạt tay là được)"
    fi
    echo "Xong. Mở wp-admin → Plugin → kiểm tra 'Vận Hành Chi Phí' đã Kích hoạt."
    ;;

  ftp)
    command -v lftp >/dev/null 2>&1 || { echo "Cần cài lftp (macOS: brew install lftp · Ubuntu: sudo apt install lftp)"; exit 1; }
    : "${VHCP_FTP_HOST:?thiếu VHCP_FTP_HOST}"
    : "${VHCP_FTP_USER:?thiếu VHCP_FTP_USER}"
    : "${VHCP_FTP_PASS:?thiếu VHCP_FTP_PASS}"
    : "${REMOTE_DIR:?thiếu VHCP_REMOTE_PLUGINS_DIR (vd /public_html/wp-content/plugins)}"
    PROTO="${VHCP_FTP_PROTO:-ftps}"   # ftps (khuyên dùng) hoặc ftp
    echo "→ lftp mirror tới ${PROTO}://${VHCP_FTP_HOST}${REMOTE_DIR}/vhcp-chi-phi/"
    lftp -c "
      set ftp:ssl-force ${VHCP_FTP_SSL_FORCE:-true};
      set ssl:verify-certificate ${VHCP_FTP_VERIFY:-true};
      open -u '${VHCP_FTP_USER}','${VHCP_FTP_PASS}' ${PROTO}://${VHCP_FTP_HOST};
      mkdir -p '${REMOTE_DIR}/vhcp-chi-phi';
      mirror -R --delete --verbose=1 --exclude-glob .git/ --exclude-glob .DS_Store \
        '$SRC/' '${REMOTE_DIR}/vhcp-chi-phi/';
    "
    echo "Xong. Vào wp-admin → Plugin → Kích hoạt 'Vận Hành Chi Phí'."
    ;;

  *)
    echo "VHCP_METHOD không hợp lệ: $METHOD (chọn ssh | ftp | zip)"; exit 1
    ;;
esac

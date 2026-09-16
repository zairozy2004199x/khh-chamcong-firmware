# Bộ skill lấy từ đâu — và luật giữ nó

Anh Thắng 16/09/2026 gửi một video TikTok (*Trạm AI Thực Chiến* — "Mỏ Vàng AI $0")
liệt kê tám repo mã nguồn mở, rồi bảo *"Tải và nạp các repo này cho anh để làm skill"*.

## Chỉ MỘT trong tám repo ấy là skill

| Repo trong video | Nó là gì | Nạp vào đây? |
|---|---|---|
| **addyosmani/agent-skills** | 25 skill cho Claude Code — Markdown thuần | ✅ **đã nạp** |
| LibreChat | web chat tự dựng (Docker + Mongo) | ❌ ứng dụng, không phải skill |
| HyperFrames | dựng motion graphics bằng code | ❌ ứng dụng |
| MoneyPrinter | tạo video ngắn tự động | ❌ ứng dụng |
| Cloudflare Agentic Inbox | lọc email bằng AI | ❌ ứng dụng |
| VoxCPM | nhân bản giọng nói | ❌ ứng dụng |
| Nango | cổng API 100+ connector | ❌ ứng dụng |
| Fincept · TradingAgents | phân tích thị trường | ❌ ứng dụng |

Bảy bộ kia là **phần mềm chạy độc lập**, mỗi bộ hàng chục tới hàng trăm MB, cần
Docker / cơ sở dữ liệu / khoá API riêng. Kéo chúng vào repo này thì repo phình lên mà
Claude Code KHÔNG đọc được gì — nó chỉ nạp `.claude/skills/*/SKILL.md`. Muốn dùng bộ
nào thì dựng riêng trên máy chủ riêng, không phải nạp vào đây.

## Nguồn của thứ đang nằm ở đây

- Repo: <https://github.com/addyosmani/agent-skills>
- Chốt ở commit `be4e44a` (11/09/2026)
- Giấy phép **MIT** — xem `LICENSE-agent-skills` cạnh tệp này. MIT đòi giữ nguyên
  dòng bản quyền khi phát tán lại, nên tệp giấy phép phải ở lại.

## 🔴 CHÉP VÀO, KHÔNG DÙNG `npx skills add`

Trang chủ bộ này hướng dẫn cài bằng `npx skills add addyosmani/agent-skills`. Ở đây thì
KHÔNG, vì ba lý do:

1. **Bộ thử phải chạy được khi mất mạng.** `tools/test/chay-het.sh` chạy trong CI và
   trên máy anh Thắng; một bước cài kéo mạng là một bước hỏng được vì lý do chẳng liên
   quan gì tới mã.
2. **Phải biết CHÍNH XÁC đang chạy chữ gì.** `npx` kéo bản mới nhất tại thời điểm chạy,
   nên hôm nay và tuần sau có thể khác nhau mà không ai thấy. Chép vào repo thì mọi
   thay đổi hiện ra trong `git diff`.
3. **Skill là chữ Claude ĐỌC RỒI LÀM THEO.** Một bản cập nhật lặng lẽ đổi cách nó viết
   mã, sửa bài kiểm, hay bấm nút — mà không ai duyệt. Đây không phải thư viện.

## Nâng bản sau này

```bash
git clone --depth 1 https://github.com/addyosmani/agent-skills.git /tmp/as
diff -ru .claude/skills /tmp/as/skills | less    # ĐỌC trước đã
```

⚠️ **Đọc `diff` trước khi chép đè.** Kéo mù là nhận cả những chỗ đổi mà không ai duyệt —
xem lý do 3 ở trên.

⚠️ Và soát lại **tệp chạy được**: bản `be4e44a` có đúng một cái,
`idea-refine/scripts/idea-refine.sh` (15 dòng, chỉ `mkdir -p docs/ideas`). Bản sau mà mọc
thêm tệp chạy được nào thì phải đọc từng dòng trước khi nhận.

```bash
find .claude/skills -type f ! -name '*.md' ! -name 'LICENSE*'
```

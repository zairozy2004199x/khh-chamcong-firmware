import { defineConfig } from "vite";
import zaloMiniApp from "zmp-vite-plugin";

/* Dùng plugin đóng gói chính thức của Zalo: nó xuất build ra `www`, chèn app-config.json và
 * đúng cấu trúc mà `zmp deploy` yêu cầu (build trần bị "Invalid project structure"). base=""
 * để asset dùng đường dẫn tương đối trong Zalo. */
export default defineConfig({
  base: "",
  plugins: [zaloMiniApp()],
});

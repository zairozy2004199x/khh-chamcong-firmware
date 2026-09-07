import { defineConfig } from "vite";

/* zmp deploy tìm thư mục build ở `www` (không phải `dist` mặc định của Vite), và app chạy trong
 * Zalo cần đường dẫn asset TƯƠNG ĐỐI (base="") vì được phục vụ từ một đường con. Cấu hình tối
 * thiểu này đủ để `zmp start` (dev) và `zmp deploy` (build ra www) chạy đúng. */
export default defineConfig({
  base: "",
  build: {
    outDir: "www",
    emptyOutDir: true,
    target: "esnext",
  },
});

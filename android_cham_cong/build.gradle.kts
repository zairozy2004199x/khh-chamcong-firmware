// Khai bản của các trình cắm, KHÔNG áp dụng ở gốc — `apply false`.
// Cùng bản với `android_thu_tien`: hai dự án trong một kho mà lệch bản trình cắm là máy CI
// tải về hai bộ công cụ, và một hôm chỉ một trong hai dựng được.
plugins {
    id("com.android.application") version "8.5.2" apply false
    id("org.jetbrains.kotlin.android") version "1.9.24" apply false
}

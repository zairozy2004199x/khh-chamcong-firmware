#pragma once
#include <cstdint>
#include <openssl/sha.h>
inline int mbedtls_sha256(const uint8_t* d, size_t n, uint8_t* out, int) {
  SHA256(d, n, out); return 0;
}

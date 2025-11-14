# CLAUDE.md — Implementation Guide for **VQ Checkout for Woo** (WooCommerce)

> **Mục tiêu:** Hướng dẫn Claude Code tạo plugin **VQ Checkout for Woo** theo kế hoạch hợp nhất V3.0, đáp ứng các tiêu chí **chính xác**, **chuẩn theo yêu cầu**, **tối ưu token**.  
> **Ngữ cảnh:** Repo này chứa bản kế hoạch & dữ liệu để xây dựng plugin WooCommerce tối ưu trang thanh toán cho thị trường Việt Nam (tỉnh/thành → quận/huyện → xã/phường), thuật toán tính phí vận chuyển theo xã/phường, bảo mật (reCAPTCHA, rate-limit), REST API, UI quản trị, testing & CI/CD.

> **Lưu ý tên gọi & quy ước (bắt buộc):**
> - File plugin chính: `VQ-woo-checkout.php`
> - PHP namespace/class prefix: `VQCheckout\...` (class) và/hoặc `class VQCheckout_*` (tương thích chuẩn WP)
> - Function prefix: `vqcheckout_...`
> - HTML id/class prefix: `vqcheckout-...`
> - Text‑domain i18n: `vq-checkout` (vi, en)

---

## 0) TL;DR — **Chế độ sinh mã “Tối ưu token”**

> **Khi làm việc trong repo này, Claude Code phải bật “TERSE MODE”**:  
> 1. **Ưu tiên sinh _code trước, lời sau_.** Chỉ giải thích ngắn gọn nếu cần.  
> 2. **Không lặp lại yêu cầu.** Không “tóm tắt lại đề bài” trước khi code.  
> 3. **Xuất diff hoặc file đầy đủ**, không xuất từng đoạn rời rạc gây dư token.  
> 4. **Tận dụng tái sử dụng** (helper, trait, service). Tránh trùng lặp logic.  
> 5. **Log ngắn gọn** (dùng `error_log`/WP logger khi cần). Không in debug dài.  
> 6. **Commit message** theo Conventional Commits, ngắn gọn, mô tả thay đổi thực tế.  
> 7. **Sinh test cùng lúc với code** (đủ cover case chính), tránh vòng lặp quay lại bổ sung.  
> 8. **Giới hạn bình luận** trong code ở mức cần thiết cho maintainability.  

---

## 1) Project Overview (theo kế hoạch hợp nhất V3.0)

Plugin giúp tối ưu trang Checkout WooCommerce cho Việt Nam & bổ sung shipping **tới cấp xã/phường**, bao gồm:

- **Checkout UX cho VN**: Thêm trường **Tỉnh/Thành → Quận/Huyện → Xã/Phường** dạng select phụ thuộc; tự động nạp dữ liệu.  
- **Tính phí vận chuyển theo xã/phường** với **Table Rate – First Match Wins**, có **điều kiện theo tổng giá đơn hàng**, hỗ trợ **block** shipping khu vực cụ thể.  
- **Quy đổi ký hiệu** `₫` → `VNĐ` (tùy chọn).  
- **Tự điền địa chỉ theo SĐT** dựa vào lịch sử đơn (privacy-by-design).  
- **Chống SPAM**: reCAPTCHA server-side, rate‑limit theo IP, từ khóa.  
- **Admin UI**: DataGrid quản lý rate (search/sort/paging), modal thêm/sửa/xóa, drag‑drop ưu tiên.  
- **REST API**: cho Address (province/district/ward), Resolve Rate, Search by Phone.  
- **Testing + CI/CD**: PHPUnit, Integration, E2E; lint + static analysis; build & release.  

**Input dữ liệu địa giới** (sẵn trong repo): `data/vietnam_provinces.json`, `data/vietnam_wards.json` (và các biến thể PHP).

---

## 2) Non‑Functional Requirements (NFR) & Chỉ tiêu kiểm chứng

- **Hiệu năng**: thời gian resolve phí **≤ 20ms** (L1/L2/L3 cache, batch‑load).  
- **Bảo mật**: reCAPTCHA v3 (fallback v2), **rate‑limit 5–10 req/10’/IP**, nonce REST, sanitize/escape đầy đủ.  
- **Tương thích**: WooCommerce HPOS, _Classic Checkout_ (P0) → **Blocks** (P1).  
- **Tính sẵn sàng**: DB migration idempotent; import dữ liệu theo batch; rollback an toàn.  
- **Chấp nhận**: P0 (cốt lõi) phải pass toàn bộ test; P1/P2 theo kế hoạch.  
- **Chuẩn mã**: WordPress Coding Standards (PHPCS), PHPStan L5, ESLint.  

---

## 3) Kiến trúc & Cấu trúc thư mục

**Tầng & modules:** Core (bootstrap/hook), API, Shipping, Data, Security, AdminUI, Cache, Utils, Tests.

**Đề xuất cấu trúc:**
```
VQ-woo-checkout.php                    (bootstrap, hooks)

/src
  /Core        Plugin.php, Service_Container.php, Hooks.php
  /API         Address_Controller.php, Rate_Controller.php, Phone_Controller.php
  /Shipping    Resolver.php, Rate_Repository.php, Location_Repository.php, WC_Method.php
  /Data        Migrations.php, Schema.php, Seeder.php, Importer.php
  /Security    Recaptcha_Service.php, RateLimiter.php, Nonce.php, Sanitizer.php
  /Admin       Settings_Page.php, Rates_Table.php, Rate_Editor.php, Assets.php
  /Cache       Cache.php, Keys.php
  /Utils       Arr.php, Str.php, Phone.php, Validation.php

/assets
  /js          admin.js, checkout.js
  /css         admin.css, checkout.css

/tests
  /phpunit     Unit/, Integration/
  /e2e         (Playwright/Cypress)

/languages     vq-checkout-vi.po, vq-checkout.pot
.github/workflows/ci.yml
composer.json, phpcs.xml, phpstan.neon, package.json, .eslintrc
```

**Prefix/quy ước:**  
- PHP namespace chính: `VQCheckout\...` (autoload PSR‑4).  
- Class name/fallback WP: `class VQCheckout_*`.  
- Hook WP/WC đăng ký trong `src/Core/Hooks.php`.  
- Khai báo tương thích HPOS + version bump tại bootstrap.

---

## 4) Dữ liệu & Thiết kế DB (tối ưu truy vấn theo `ward_code`)

**Bảng cốt lõi (InnoDB, utf8mb4):**

1. `wp_vqcheckout_ward_rates` — định nghĩa “rate” (title, cost, priority, flags, conditions theo subtotal).  
2. `wp_vqcheckout_rate_locations` — ánh xạ `rate_id` ↔ danh sách `ward_code` (một rate nhiều xã).  
3. `wp_vqcheckout_security_log` — log bảo mật (IP, action, score, quyết định).

**Chỉ số:** index theo `ward_code`, `priority`, composite cần thiết.  
**Migration:** dùng `dbDelta`, versioned, idempotent.  
**Seeder/Importer:** nạp JSON `data/vietnam_*` vào bảng location/metadata theo batch (WP‑CLI + WP‑Cron để tránh timeout).  
**Xóa/cập nhật:** cascade quan hệ trên `rate_locations` khi xóa `rate`.

---

## 5) REST API (WP REST) — Contract tối giản & an toàn

**Namespace:** `vqcheckout/v1`  
**Yêu cầu chung:** Nonce WP hoặc token, verify reCAPTCHA (khi cần), sanitize input, limit rate.

- `GET /address/provinces` → Danh sách {code, name}.  
- `GET /address/districts?province=01` → Danh sách quận/huyện.  
- `GET /address/wards?district=010` → Danh sách xã/phường.  
- `POST /rates/resolve`  
  - **Body**: `{ ward_code, cart_subtotal, items=[{id,qty,weight?...}] }`  
  - **Trả về**: `{ rate_id, label, cost, currency, meta, cache_hit }`  
- `POST /phone/lookup` (opt‑in)  
  - **Body**: `{ phone }` → **ẩn tối đa dữ liệu**, chỉ trả address đủ dùng (privacy-by-design).

**Lỗi chuẩn hóa**: `WP_Error` với mã & HTTP status phù hợp.

---

## 6) Thuật toán **Shipping Resolver** (First Match Wins)

**Nguyên tắc:**  
- Lọc ứng viên theo `ward_code` (n=K), sort bởi `priority` (ASC), **dừng ngay khi match**.  
- Điều kiện subtotal theo khoảng `[min, max]`, hỗ trợ free‑ship > ngưỡng.  
- **Flags**: “block shipping” (trả kết quả “không giao”); “stop processing” (FMV).  
- **Cache‑first** (L1 runtime → L2 object cache → L3 transient/Redis) theo key `(ward, subtotal_bucket)`.

**Pseudocode rút gọn:**
```php
$result = $cache->get($key);
if ($result) return $result;

$rates = $repo->get_rates_for_ward($ward_code); // đã sort priority ASC
foreach ($rates as $rate) {
  if ($rate->is_blocked()) return $cache->set($key, $rate->as_blocked());
  $cost = $rate->match_subtotal($cart_subtotal);
  if ($cost !== null) {
    $out = ['rate_id'=>$rate->id, 'label'=>$rate->title, 'cost'=>$cost];
    if ($rate->stop_after_match) return $cache->set($key, $out);
    $best = $best ?? $out;
  }
}
return $cache->set($key, $best ?? $default);
```

**Độ phức tạp:** O(K) với K rất nhỏ sau khi lọc theo `ward_code` + index.  

---

## 7) Checkout UI (Frontend)

- Thêm 3 trường **Province / District / Ward** (dependent selects, `Select2`/vanilla).  
- Tải danh mục qua REST; cache 5–15 phút client‑side (localStorage) + debounce.  
- Validate: bắt buộc chọn ward hợp lệ; normalize display text.  
- Tùy chọn đổi symbol tiền tệ `₫`→`VNĐ`.  
- (P1) Tích hợp Woo **Blocks** qua Store API extension (sau khi P0 ổn định).

---

## 8) Admin UI

- **Menu**: “VQ Checkout” → Tabs: General, Security, Checkout, Display, Shipping Rates.  
- **DataGrid Rates**: paging/search/sort; modal thêm/sửa; **drag‑drop priority**.  
- Form Rate: chọn nhiều **wards**, đặt điều kiện subtotal (nhiều dòng), flags.  
- Import/Export JSON rates; bulk actions.  
- Tải/lưu qua REST, nonce bắt buộc.

---

## 9) Bảo mật

- **reCAPTCHA v3** (ngưỡng ≥ 0.5, fallback v2), verify server‑side.  
- **Rate‑limit**: 5–10 req/10 phút/IP cho các API nhạy cảm.  
- **Nonce** cho REST, **capability checks** cho Admin.  
- **Sanitize/Escape** mọi input/output; **prepared statements**.  
- **Audit log** tối thiểu vào `vqcheckout_security_log` (IP, action, score, allow/deny).

---

## 10) Caching Strategy

- **L1**: runtime static array (per‑request).  
- **L2**: WP Object Cache (APCu/Memcached/Redis nếu có).  
- **L3**: Transient/Redis theo key chuẩn hóa; TTL hợp lý; **invalidation thông minh** khi: cập nhật rate, map ward, đổi settings.  
- **Preheat** cache theo khu vực bán chạy (tùy chọn).

---

## 11) Tích hợp WooCommerce

- **HPOS**: khai báo support, dùng CRUD API, không truy cập bảng cũ trực tiếp.  
- **Classic Checkout**: hook `woocommerce_checkout_fields`, `woocommerce_after_checkout_form`, v.v.  
- **Shipping Method**: đăng ký class `WC_Shipping_VQCheckout_Ward_Rate` gọi `Resolver` để tính phí.  
- **Ẩn tiêu đề phương thức vận chuyển** (filter phù hợp).  
- **Không chặn core flow** khi dữ liệu chưa import: có fallback.

---

## 12) Testing & QA

- **Unit (PHPUnit)**: Resolver, Repo, Cache, Security.  
- **Integration**: REST controllers, migrations, seeder.  
- **E2E**: tạo khu vực giao hàng, thêm phương thức, tính phí theo ward, free‑ship theo tổng.  
- **Performance**: benchmark resolve (≤ 20ms), cache hit ratio.  
- **Security**: captcha pass/fail, rate‑limit, input fuzz.  
- **Coverage**: nhắm ≥ 80% core.  
- **Fixtures**: subset JSON cho test; WP test bootstrap.

---

## 13) CI/CD (GitHub Actions)

**Stages:** PHPCS, PHPStan, ESLint → Unit/Integration → E2E → Security scan → Build (zip) → Release (tag + artifact).  
- Lint/Type check không quá khắt khe nhưng **không bỏ qua lỗi thật**.  
- Artifacts: `dist/vq-checkout.zip` (đầy đủ plugin).  
- Manual approval trước khi deploy production.

---

## 14) Quy ước mã & Thực hành

- Chuẩn WP (PHPCS), **PHP ≥ 7.4** (ưu tiên 8.x), strict types khi có thể.  
- Xử lý lỗi bằng `WP_Error`/exception, không để die/exit trong flow chính.  
- i18n (`__()`, `_e()`, domain `vq-checkout`), vi/en.  
- Không commit secrets; dùng env/secret store của CI.  
- Ghi chú kiến trúc quan trọng bằng docblock ngắn gọn.

---

## 15) **Hướng dẫn làm việc với Sub‑agents** (bắt buộc)

### 15.1 Planner‑Researcher → **Sinh kế hoạch chi tiết**
- Tạo thư mục `./plans` và file `./plans/IMPLEMENTATION_PLAN.md` gồm:
  - Phân rã nhiệm vụ theo **P0 → P1 → P2**; mỗi task: mô tả + DoD + ước lượng.  
  - Danh mục file/diff cần tạo.  
  - Bảng ánh xạ yêu cầu ↔ module ↔ test.  
- Tạo checklist **Go/No‑Go** & acceptance criteria.  
- Đọc kỹ các file kế hoạch trong `plan/` rồi đối chiếu.

### 15.2 Implementation (Claude Code)
- Tuân thủ kế hoạch `./plans/IMPLEMENTATION_PLAN.md`.  
- Sinh **từng PR nhỏ**: migrations + importer → resolver + repo → REST → admin UI → checkout UI → polish.  
- Mỗi PR **kèm test** tương ứng, CI phải xanh.

### 15.3 Tester
- Chạy toàn bộ suite; báo cáo coverage, cases fail, performance. Đưa khuyến nghị sửa.  

### 15.4 Debugger
- Khi có lỗi/CI đỏ: thu log (server/GitHub Actions), xác định bottleneck/bug, đề xuất fix.  

### 15.5 Code‑Reviewer
- So sánh với kế hoạch `./plans/IMPLEMENTATION_PLAN.md`; kiểm các điểm: chuẩn API, security, perf, HPOS.  

### 15.6 Docs‑Manager
- Cập nhật `./docs` (hướng dẫn cài đặt, config, nhập dữ liệu, FAQ). Chèn screenshot từ `docs/screenshots/` nếu cần.

---

## 16) **Roadmap P0/P1/P2**

- **P0 (phải có trước khi phát hành):**
  - DB migrations + importer JSON; Resolver (FMV) + cache; REST Address/Resolve; Checkout VN fields; Admin Rates; reCAPTCHA + rate‑limit; Unit/Integration tests; CI lint+unit; HPOS support.

- **P1 (nên có sớm):**
  - E2E tests; Export/Import rates UI; Woo Blocks support; Tự điền địa chỉ theo SĐT; Performance monitor lightweight.

- **P2 (có thể sau):**
  - Monitor nâng cao; preheat cache; multi‑currency; UX nâng cao.

---

## 17) **Deliverables & Definition of Done (DoD)**

- `dist/vq-checkout.zip` cài được trên WP + Woo mới nhất, **không lỗi fatal**, pass P0 tests.  
- Tài liệu `README.md` + `docs/` hướng dẫn setup & import dữ liệu.  
- CI xanh: lint + unit/integration; báo cáo coverage.  
- Mọi feature P0 có test; **resolve ≤ 20ms** trên dataset mẫu.

---

## 18) Ghi chú dành cho Claude Code

- Khi bối rối, **đọc lại các file trong `plan/`** và `docs/woocheckout.md`.  
- **Không** thêm watermark/AI attribution vào commit/message.  
- Ưu tiên **tạo mã hoàn chỉnh & chạy được** hơn là mô tả dài.  
- Với thay đổi lớn, hãy **đề xuất cấu trúc file trước**, sau đó sinh full files.

---

**Nguồn định hướng gốc:** file hướng dẫn ban đầu trong repo (đã được thay thế bởi tài liệu này).
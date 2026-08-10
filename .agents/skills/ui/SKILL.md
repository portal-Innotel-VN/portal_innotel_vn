---
name: ui
description: >-
  Bộ tiêu chuẩn UI/UX CRM cho portal_18. Khi tạo hoặc chỉnh sửa bất kỳ
  view, component, CSS hoặc giao diện nào trong dự án, Agent PHẢI tuân thủ
  bộ quy tắc thiết kế này để đảm bảo tính đồng bộ, chuyên nghiệp và
  hiện đại trên toàn bộ ứng dụng.
---

# Portal 18 — CRM UI/UX Design System

> **Mục tiêu**: Mọi giao diện mới hoặc cải tiến trong portal_18 đều PHẢI
> tuân thủ bộ tiêu chuẩn này để đảm bảo sự đồng nhất, chuyên nghiệp và
> trải nghiệm người dùng cao cấp.

---

## 1. Nền tảng kỹ thuật (Tech Foundation)

| Thành phần          | Giá trị hiện tại                                         |
| ------------------- | -------------------------------------------------------- |
| CSS Framework       | Bootstrap 3 (đã custom nặng qua `bs-overides.css`)      |
| Grid System         | Bootstrap grid 12 cột + CSS Grid cho module mới          |
| Icon Library        | Font Awesome 4 + Glyphicons Halflings                    |
| Theme Engine        | Module `theme_style` — inject CSS qua hook `app_admin_head` |
| Build Tool          | Grunt (xem `Gruntfile.js`)                                |

### Quy tắc chung

- **Không sửa file core** (`assets/css/style.css`, `bs-overides.css`) trừ khi
  được yêu cầu rõ ràng. Ưu tiên viết CSS mới trong thư mục `modules/<tên>/assets/css/`.
- CSS mới **PHẢI** sử dụng CSS Custom Properties (variables) để dễ bảo trì.
- Sử dụng **BEM-like naming** với prefix module (vd: `sp-`, `tech-`, `pr-`)
  để tránh xung đột với core CSS.
- Luôn đặt `box-sizing: border-box` cho container gốc module.

---

## 2. Bảng màu (Color Palette)

### 2.1 Màu hệ thống gốc (System Colors)

Các giá trị dưới đây được trích xuất trực tiếp từ `style.css` và
`bs-overides.css`. **KHÔNG** thay thế bằng giá trị khác.

```css
/* ── Core Brand ── */
--crm-primary:          #28b8da;   /* btn-primary, brand accent */
--crm-primary-hover:    #1e95b1;
--crm-primary-active:   #197b92;

/* ── Semantic ── */
--crm-success:          #84C529;   /* btn-success, tác vụ hoàn thành */
--crm-success-hover:    #74B31B;
--crm-info:             #03a9f4;   /* btn-info, pagination active, focus ring */
--crm-info-hover:       #0286c2;
--crm-warning:          #ff6f00;   /* btn-warning, cảnh báo */
--crm-warning-hover:    #cc5900;
--crm-danger:           #fc2d42;   /* btn-danger, lỗi, xóa */
--crm-danger-hover:     #f3031c;

/* ── Neutrals ── */
--crm-ink:              #323a45;   /* body text chính */
--crm-muted:            #6c7888;   /* text phụ, placeholder */
--crm-text-secondary:   #7c838b;   /* meta, caption */
--crm-link:             #008ece;   /* <a> link */
--crm-link-hover:       #004B6D;

/* ── Surfaces ── */
--crm-bg-body:          #626f80;   /* body background */
--crm-bg-white:         #ffffff;   /* card, panel background */
--crm-bg-soft:          #f7f8fa;   /* panel heading, surface nhẹ */
--crm-bg-alt:           #F1F5F7;   /* btn-default background */

/* ── Borders ── */
--crm-border:           #e4e8f1;   /* viền card/panel mới (module mới) */
--crm-border-panel:     #dce1ef;   /* viền panel_s.panel-body gốc */
--crm-border-input:     #d6d6d6;   /* viền input/select */
--crm-border-subtle:    #E6E9EB;   /* viền btn-default */

/* ── Header / Navigation ── */
--crm-header:           #415165;   /* header gradient start, sidebar */
--crm-header-mid:       #51647c;   /* header gradient mid */
--crm-header-end:       #4f5d7a;   /* header gradient end */
--crm-sidebar:          #626f80;   /* #side-menu background */
--crm-sidebar-active:   #e3e8ee;   /* menu active item bg */
--crm-sidebar-active-text: #323a45;

/* ── Modal ── */
--crm-modal-header-start: #226faa;
--crm-modal-header-mid:   #2989d8;
--crm-modal-header-end:   #72c0d3;

/* ── Badge ── */
--crm-badge-bg:         #6c7888;
```

### 2.2 Màu trạng thái CRM mở rộng (CRM Status Colors)

Dùng cho Deal stages, Lead status, Pipeline tags:

```css
/* ── Pipeline / Deal Stages ── */
--crm-stage-new:         #03a9f4;   /* Mới / Tiếp nhận */
--crm-stage-contact:     #28b8da;   /* Đang liên hệ */
--crm-stage-proposal:    #ff6f00;   /* Đã gửi báo giá */
--crm-stage-negotiation: #f59e0b;   /* Đang đàm phán */
--crm-stage-won:         #84C529;   /* Thắng / Chốt thành công */
--crm-stage-lost:        #fc2d42;   /* Thua / Thất bại */

/* ── Priority Tags ── */
--crm-priority-urgent:   #fc2d42;
--crm-priority-high:     #ff6f00;
--crm-priority-medium:   #f59e0b;
--crm-priority-low:      #84C529;
--crm-priority-none:     #6c7888;

/* ── Leaderboard Ranks ── */
--crm-rank-gold:         #fff0c9;   /* bg rank 1 */
--crm-rank-gold-text:    #8b6216;
--crm-rank-silver:       #e8edf2;   /* bg rank 2 */
--crm-rank-silver-text:  #526170;
--crm-rank-bronze:       #f2e2d7;   /* bg rank 3 */
--crm-rank-bronze-text:  #8b5837;
```

### 2.3 Quy tắc sử dụng màu

1. **KHÔNG dùng màu thuần** (`red`, `blue`, `green`). Luôn dùng mã hex
   từ bảng trên hoặc CSS variable tương ứng.
2. **Gradient header**: Luôn dùng `linear-gradient(to right, var(--crm-header) 0%, var(--crm-header-mid) 60%, var(--crm-header-end) 100%)`.
3. **Trạng thái hover**: Darken 10-15% so với màu gốc, đã định nghĩa sẵn
   trong các biến `*-hover`.
4. **Background tối cho overlay**: `rgba(50, 58, 69, 0.48)` (đã dùng trong drawer backdrop).
5. **Focus ring** cho input: Border color `#03a9f4` (crm-info).

---

## 3. Typography (Kiểu chữ)

### 3.1 Font Stack

```css
/* Font chính — dùng cho toàn bộ ứng dụng */
font-family: "Roboto", "Helvetica Neue", Helvetica, Arial, sans-serif;

/* Font phụ — dùng cho badge, indicator nhỏ */
font-family: Verdana, serif;

/* Font code/mono */
font-family: Consolas, monospace;
```

> **Quy tắc**: Mọi component mới PHẢI kế thừa font từ `body`. Chỉ khai báo
> `font-family` nếu cần font khác (mono cho code block).

### 3.2 Font Size Scale

| Token            | Giá trị   | Sử dụng                                      |
| ---------------- | --------- | --------------------------------------------- |
| `--fs-2xs`       | `9px`     | Micro label, timestamp feed                    |
| `--fs-xs`        | `10px`    | Drawer label uppercase, staff subtitle         |
| `--fs-sm`        | `11px`    | Badge text, indicator                          |
| `--fs-body-sm`   | `12px`    | Table header, button action text, caption      |
| `--fs-body`      | `13px`    | **Body text mặc định**, side-menu, form label  |
| `--fs-body-md`   | `13.5px`  | Button text                                    |
| `--fs-md`        | `14px`    | Alert, form input, heading h5, table cell      |
| `--fs-lg`        | `15px`    | Drawer toolbar title                           |
| `--fs-section`   | `16px`    | Section heading (leaderboard, feed header)     |
| `--fs-menu-icon` | `17px`    | Menu icon, sub-section title                   |
| `--fs-xl`        | `18px`    | Alert icon                                     |
| `--fs-2xl`       | `20px`    | H3 tương đương                                 |
| `--fs-title`     | `22px`    | Dashboard page title (h1)                      |
| `--fs-hero`      | `25-26px` | Logo text, stat highlight                      |
| `--fs-stat`      | `28px`    | KPI big number (summary card)                  |
| `--fs-display`   | `36px`    | Display số lớn, hero stat                      |

### 3.3 Font Weight

| Token    | Giá trị | Sử dụng                                           |
| -------- | ------- | -------------------------------------------------- |
| Regular  | `400`   | Body text, heading h3/h4, badge                    |
| Medium   | `500`   | Label, `<b>`/`<strong>`, panel heading, form label  |
| SemiBold | `600`   | Button action, staff name active                   |
| Bold     | `700`   | Page title, card value, table header, KPI           |
| ExtraBold| `800`   | Table column header (uppercase)                     |

### 3.4 Line Height

- Body text: `1.42857` (Bootstrap default)
- Heading / title: `1.35`
- Table cell: `1.3 – 1.45`
- KPI big number: `1.25`

---

## 4. Spacing & Layout

### 4.1 Spacing Scale

```css
--space-2:   2px;     /* micro gap */
--space-4:   4px;     /* icon-to-text, tight padding */
--space-5:   5px;     /* inline gap */
--space-7:   7px;     /* label margin-bottom */
--space-8:   8px;     /* button gap, small padding */
--space-10:  10px;    /* panel heading padding, button gap */
--space-12:  12px;    /* grid gap, table cell padding, feed item padding */
--space-15:  15px;    /* panel-body standard, alert padding */
--space-16:  16px;    /* side-menu padding-left */
--space-18:  18px;    /* dashboard grid gap, modal header padding */
--space-20:  20px;    /* panel-body padding, drawer content padding */
--space-24:  24px;    /* commandbar gap */
--space-25:  25px;    /* panel_s margin-bottom */
--space-26:  26px;    /* commandbar horizontal padding */
--space-30:  30px;    /* empty state padding */
```

### 4.2 Layout Grid

```css
/* Dashboard summary grid — 3 cột đều */
display: grid;
grid-template-columns: repeat(3, minmax(0, 1fr));
gap: 12px;
padding: 18px;

/* Main content grid */
display: grid;
grid-template-columns: 1fr;    /* stack on mobile */
gap: 18px;
margin-top: 18px;

/* 2-column layout (desktop) */
@media (min-width: 1200px) {
    grid-template-columns: 1fr 400px;
}
```

### 4.3 Container & Wrapper

- `#wrapper` padding-left: `220px` (desktop sidebar) / `0` (mobile).
- `.content` padding: `20px`.
- Header height: `63px`.
- Sidebar width: `220px`.

---

## 5. Border & Radius

### 5.1 Border Radius Scale

| Token         | Giá trị | Sử dụng                                        |
| ------------- | ------- | ----------------------------------------------- |
| `--radius-xs` | `2px`   | Tag nhỏ, indicator                               |
| `--radius-sm` | `3-4px` | Panel body, form control, tag, badge, status tag |
| `--radius-md` | `5-6px` | Button, modal content, card, staff trigger       |
| `--radius-lg` | `8px`   | Dashboard overview card, summary card, log card  |
| `--radius-xl` | `16px`  | Chip-circle                                      |
| `--radius-pill`| `50px` | Pill badge, header active link                   |
| `--radius-circle`| `50%`| Avatar, rank badge                               |

### 5.2 Border Style

```css
/* Standard card/panel border */
border: 1px solid var(--crm-border);          /* #e4e8f1 — module mới */
border: 1px solid var(--crm-border-panel);     /* #dce1ef — panel_s gốc */

/* Input border */
border: 1px solid var(--crm-border-input);     /* #d6d6d6 */

/* Accent border (action feed left) */
border-left: 3px solid var(--crm-danger);      /* urgent item */
border-left: 3px solid var(--crm-warning);     /* responded item */

/* Top accent stripe (summary card) */
.card::before {
    height: 3px;
    background: var(--crm-success);  /* or warning, danger */
}
```

---

## 6. Shadow (Elevation)

```css
/* Level 0 — Flat */
box-shadow: none;

/* Level 1 — Subtle card (dashboard summary card) */
box-shadow: 0 1px 1px rgba(0, 0, 0, 0.03);

/* Level 2 — Panel (.panel_s) */
box-shadow: 0 1px 15px 1px rgba(90, 90, 90, 0.08);

/* Level 3 — Modal content */
box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05), 0 2px 4px 0 rgba(0, 0, 0, 0.2);

/* Level 4 — Drawer panel */
box-shadow: -10px 0 30px rgba(50, 58, 69, 0.2);

/* Utility — Avatar ring */
box-shadow: 0 0 0 1px #d5dde5;

/* Utility — Focus ring */
box-shadow: 0 0 0 2px var(--crm-info);  /* #03a9f4 */
```

---

## 7. Component Patterns

### 7.1 Buttons

```css
/* Tất cả button */
.btn {
    text-transform: uppercase;
    font-size: 13.5px;
    padding: 5px 10px;
    border: 0;
    outline-offset: 0;
    transition: all 0.15s ease-in-out;
}

/* Kích thước button module mới (dashboard actions) */
.module-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 38px;
    gap: 8px;
    padding: 8px 13px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

/* Icon-only button */
.icon-btn {
    width: 38px;
    height: 38px;
    padding: 0;
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 6px;
}
```

**Quy tắc**: Không tạo biến thể button mới. Sử dụng hệ thống Bootstrap
gốc (`btn-primary`, `btn-success`, `btn-info`, `btn-warning`, `btn-danger`,
`btn-default`) hoặc module-specific action button với prefix.

### 7.2 Cards / Panels

```css
/* Panel gốc (.panel_s) — dùng cho các trang truyền thống */
.panel_s {
    background: none;
    border: none;
    box-shadow: 0 1px 15px 1px rgba(90, 90, 90, 0.08);
    margin-bottom: 25px;
}

.panel_s .panel-body {
    background: #fff;
    border: 1px solid #dce1ef;
    border-radius: 4px;
    padding: 20px;
}

/* Summary card — module mới (dashboard) */
.summary-card {
    position: relative;
    padding: 17px 18px 15px;
    border: 1px solid var(--crm-border);
    border-radius: 8px;
    background: var(--crm-bg-white);
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.03);
}

/* Top accent stripe */
.summary-card::before {
    position: absolute;
    top: 0; right: 0; left: 0;
    height: 3px;
    content: '';
    background: var(--crm-muted);
}
```

### 7.3 Forms

```css
/* Input mặc định */
input, select, textarea {
    padding: 5px 10px;
    border: 1px solid #d6d6d6;
    box-shadow: none;
    color: #494949;
    font-size: 14px;
    height: 36px;
    transition: border-color 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
}

/* Focus state */
input:focus, select:focus, textarea:focus {
    border-color: #03a9f4;
    box-shadow: none;
    outline: 0;
}

/* Label */
label, .control-label {
    font-weight: 500;
    font-size: 13px;
    color: #4a4a4a;
    margin-bottom: 7px;
}
```

**Quy tắc**: Giữ nguyên height `36px` cho input. Form group margin-bottom `15px`.

### 7.4 Tables

```css
/* Table header */
th {
    height: 46px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--crm-border);
    background: var(--crm-bg-soft);
    color: var(--crm-ink);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

/* Table cell */
td {
    height: 76px;  /* đủ rộng cho avatar + 2 dòng text */
    padding: 11px 12px;
    border-bottom: 1px solid var(--crm-border);
    vertical-align: middle;
}

/* Row hover */
tbody tr:hover td {
    background: #fbfdff;
}
```

### 7.5 Modal

```css
/* Modal header — gradient */
.modal-header {
    background: linear-gradient(
        to right,
        var(--crm-modal-header-start) 0%,
        var(--crm-modal-header-mid)   37%,
        var(--crm-modal-header-end)   100%
    );
    border-radius: 6px 6px 0 0;
    color: #fff;
    padding: 18px;
    border-color: transparent;
}

/* Modal content */
.modal-content {
    border-radius: 6px;
    border: 0;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05),
                0 2px 4px 0 rgba(0, 0, 0, 0.2);
}
```

### 7.6 Drawer (Slide-over Panel)

```css
/* Backdrop */
.drawer__backdrop {
    background: rgba(50, 58, 69, 0.48);
    transition: opacity 0.2s ease;
}

/* Panel */
.drawer__panel {
    width: min(760px, 92vw);
    background: #f7f8fa;
    box-shadow: -10px 0 30px rgba(50, 58, 69, 0.2);
    transform: translateX(100%);
    transition: transform 0.24s ease;
}

/* Toolbar */
.drawer__toolbar {
    min-height: 68px;
    padding: 12px 18px;
    border-bottom: 1px solid var(--crm-border);
    background: #ffffff;
}
```

### 7.7 Avatar

```css
/* Standard avatar */
.avatar {
    width: 38px;
    height: 38px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 0 0 1px #d5dde5;
}

/* Small avatar (feed) */
.avatar--sm {
    width: 34px;
    height: 34px;
}
```

### 7.8 Status Badge / Tag

```css
/* Status tag nhỏ */
.status-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 7px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.2;
}

/* Danger status */
.status-tag--danger {
    background: #fff0f1;
    color: #d91f35;
}

/* Warning status (responded) */
.status-tag--warning {
    background: #fef3e0;
    color: #cc5900;
}

/* Success status */
.status-tag--success {
    background: #f0f9e0;
    color: #5a8f12;
}

/* Chip / Pill badge */
.chip {
    display: inline-block;
    height: 32px;
    font-size: 13px;
    line-height: 32px;
    padding: 0 12px;
    color: #fff;
    border-radius: 16px;
    background: #4b5158;
}
```

### 7.9 Progress Bar

```css
.progress {
    position: relative;
    width: 100%;
    height: 5px;
    border-radius: 3px;
    background: #e4e8f1;
    overflow: hidden;
}

.progress__bar {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--crm-muted);
}
```

---

## 8. Animations & Transitions

### 8.1 Transition Timing Functions

```css
/* Default — dùng cho button, link hover */
transition: all 0.15s ease-in-out;

/* Color change — dùng cho button, icon */
transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;

/* Input focus */
transition: border-color 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);

/* Overlay fade */
transition: opacity 0.2s ease;

/* Drawer slide */
transition: transform 0.24s ease;

/* Panel heading */
transition: all 0.3s;

/* Tab filter */
transition: background 0.15s ease, border-color 0.15s ease;
```

### 8.2 Quy tắc Animation

1. **KHÔNG** dùng animation dài hơn `0.4s` cho UI interaction.
2. **Micro-interaction** (hover, focus, toggle): `0.15s – 0.24s`.
3. **Entrance** (drawer, modal): `0.2s – 0.3s`.
4. **Mặc định dùng `ease`** hoặc `ease-in-out`. Chỉ dùng `cubic-bezier`
   cho input focus.
5. Trên mobile `@media (max-width: 767px)`, **DISABLE** transition cho
   drawer/overlay để đảm bảo hiệu năng: `transition: none;`.

---

## 9. Responsive Breakpoints

```css
/* Mobile first */
@media (max-width: 767px)  { /* Phone */       }
@media (min-width: 768px)  { /* Tablet */      }
@media (min-width: 992px)  { /* Desktop SM */  }
@media (min-width: 1200px) { /* Desktop LG */  }
@media (min-width: 1400px) { /* Desktop XL */  }
```

### 9.1 Quy tắc Responsive

1. **Summary grid**: 3 cột → `repeat(2, 1fr)` ở `≤992px` → `1fr` ở `≤600px`.
2. **Leaderboard table**: `overflow-x: auto` + `min-width: 1080px`.
3. **Drawer**: Luôn `width: min(760px, 92vw)` — tự co trên mobile.
4. **Sidebar**: Ẩn bằng class `hide-sidebar` / `show-sidebar`.
5. **Dashboard commandbar**: `flex-wrap: wrap` trên `≤600px`, gap thu hẹp
   xuống `12px`.

---

## 10. Navigation & Header

```css
/* Header bar */
#header {
    background: linear-gradient(to right, #415165, #51647c 26%, #51647c 73%, #4f5d7a);
    height: 63px;
    z-index: 99;
}

/* Sidebar menu */
#side-menu {
    background: #626f80;
}

#side-menu li a {
    color: #fff;
    text-transform: uppercase;
    padding: 11px 20px 11px 16px;
    font-size: 13px;
    font-family: 'Roboto';
}

/* Active state */
#side-menu li.active > a {
    border-radius: 0;
    color: #323a45;
    background: #e3e8ee;
}

/* Menu icon */
.menu-icon {
    margin-right: 16px;
    width: 18px;
    font-size: 17px;
}
```

---

## 11. Accessibility & UX Checklist

Mọi component mới PHẢI đáp ứng:

- [ ] **Contrast ratio** ≥ 4.5:1 cho text trên background.
- [ ] **Focus visible**: Mọi interactive element phải có focus style rõ ràng.
- [ ] **Keyboard navigation**: Tab order logic, Enter/Space kích hoạt.
- [ ] **Touch target**: Tối thiểu `38×38px` cho nút bấm trên mobile.
- [ ] **Loading state**: Hiển thị skeleton hoặc spinner khi fetch dữ liệu.
- [ ] **Empty state**: Hiển thị message + icon khi không có dữ liệu.
- [ ] **Error state**: Border đỏ (`--crm-danger`) + message text rõ ràng.
- [ ] **RTL support**: Kiểm tra layout nếu cần hỗ trợ RTL (dự án có CSS RTL).

---

## 12. CRM-Specific Patterns

### 12.1 Dashboard KPI Card

```
┌─────────────────────────────┐
│▀▀▀▀▀▀▀▀▀ (3px accent bar) ▀│  ← màu theo trạng thái
│  [Label]         [Period]   │
│                             │
│  28,500,000đ                │  ← --fs-stat, font-weight 700
│  ████████░░░░  75%          │  ← progress bar
└─────────────────────────────┘
```

### 12.2 Kanban Pipeline Card

```
┌──────────────────┐
│ Deal Name        │  ← 14px, bold
│ Company • Stage  │  ← 12px, muted
│ 150,000,000đ     │  ← 16px, success green nếu won
│ [Tag] [Tag]      │  ← status-tag
│ ───────          │
│ 👤 Staff  📅 Due │  ← avatar-sm + date
└──────────────────┘
```

### 12.3 Action Feed Item

```
┌ 3px danger border ───────────────────┐
│ [Avatar] Staff Name                  │
│          Role • 2 phút trước         │
│                          [Quá hạn]   │  ← status-tag--danger
│ Customer: Nguyễn Văn A               │
│ Task: Gọi điện xác nhận hợp đồng    │
│ [Gọi ngay] [Bỏ qua]                 │  ← btn-info, btn-default
└──────────────────────────────────────┘
```

### 12.4 Leaderboard Table Row

```
│ 🥇 │ [Av] Tên NV    │ Pipeline │ Doanh thu │ Tỷ lệ   │ Cơ hội │
│ #1 │      Phòng ban  │ █████░  │ 150M      │ 75%      │ 12     │
```

---

## 13. Do's and Don'ts

### ✅ DO

- Dùng CSS Custom Properties cho mọi giá trị lặp lại.
- Prefix class name với tên module (`sp-`, `tech-`, `cr-`).
- Sử dụng `gap` thay vì `margin` trong flex/grid container.
- Dùng `min-width: 0` trong flex children để tránh overflow.
- Đặt `text-overflow: ellipsis` cho text có thể tràn.
- Test trên ≥ 3 breakpoints trước khi commit.
- Sử dụng `transition` cho mọi state change (hover, active, focus).
- Dùng gradient header giống hệ thống cho mọi commandbar module mới.

### ❌ DON'T

- ❌ Hardcode màu trực tiếp — luôn dùng variable.
- ❌ Dùng `!important` trừ khi override core CSS bắt buộc.
- ❌ Tạo font-size ngoài scale đã định nghĩa.
- ❌ Dùng `box-shadow` lớn hơn Level 4 (drawer).
- ❌ Inline style trong PHP view — tách ra file CSS riêng.
- ❌ Thay đổi `body` font-family, font-size, color.
- ❌ Xóa hoặc ghi đè `bs-overides.css` / `style.css`.
- ❌ Dùng animation > 0.4s cho micro-interaction.
- ❌ Quên RTL mirror cho `margin-left`/`padding-left` layout.
- ❌ Bỏ qua empty state và loading state.

---

## 14. File Organization

```
modules/<module_name>/
├── assets/
│   ├── css/
│   │   └── <module>.css          ← CSS riêng, dùng CSS variables
│   └── js/
│       └── <module>.js
├── views/
│   ├── dashboard.php             ← main view
│   └── partials/
│       ├── _card.php
│       ├── _table.php
│       └── _drawer.php
└── <module>.php                  ← register CSS/JS qua hooks
```

**Hook đăng ký CSS**:
```php
hooks()->add_action('app_admin_head', function() {
    echo '<link rel="stylesheet"
           href="' . module_dir_url(MODULE_NAME, 'assets/css/module.css') . '">';
});
```

---

## 15. Khi nào KHÔNG áp dụng skill này

- Sửa lỗi logic PHP / database — không liên quan UI.
- Viết migration, model, hoặc API endpoint — không có giao diện.
- Chỉnh sửa language file — không ảnh hưởng styling.
- Task được yêu cầu rõ ràng "không thay đổi giao diện".

---

*Skill version: 1.0.0 — Audited from portal_18 codebase on 2026-08-10.*

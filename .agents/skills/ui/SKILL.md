---
name: ui
description: >-
  Bộ tiêu chuẩn UI/UX CRM & Frontend Design cho portal_18. Khi tạo hoặc chỉnh sửa bất kỳ
  view, component, CSS hoặc giao diện nào trong dự án, Agent PHẢI tuân thủ
  bộ quy tắc thiết kế này để đảm bảo tính đồng bộ, chuyên nghiệp, cá tính và
  hiện đại trên toàn bộ ứng dụng.
---

# Portal 18 — CRM UI/UX & Frontend Design System

> **Mục tiêu**: Mọi giao diện mới hoặc cải tiến trong portal_18 đều PHẢI tuân thủ bộ tiêu chuẩn này để đảm bảo sự đồng nhất kỹ thuật, tính chuyên nghiệp, trải nghiệm người dùng cao cấp và có dấu ấn thiết kế đặc trưng (distinctive design).

---

## 1. Triết lý Thiết kế & Định hướng Mỹ thuật (Design Philosophy & Aesthetic Direction)

Tiếp cận phát triển UI với tinh thần của một **Lead Designer**: tạo ra giao diện sắc nét, chuyên nghiệp cho hệ thống CRM nhưng không rập khuôn hay vô hồn.

### 1.1 Nguyên tắc cốt lõi (Core Principles)
1. **Gắn liền với Ngữ cảnh Nghiệp vụ (Ground in the Subject)**:
   - Hiểu rõ đối tượng người dùng (Sales, Admin, CSKH), mục đích của trang và dữ liệu thực tế.
   - **Tuyệt đối không dùng dữ liệu giả vô nghĩa (`Lorem Ipsum`)**. Sử dụng đúng ngữ cảnh dữ liệu CRM (khách hàng, hợp đồng, báo giá, doanh số).
2. **Điểm nhấn Đặc trưng (Signature Element)**:
   - Mỗi view chính nên có **1 chi tiết độc đáo duy nhất** đại diện cho nhận diện trang (vd: KPI Summary Card tương tác cao, thanh tiến trình Pipeline sống động, hoặc bảng xếp hạng Leaderboard độc đáo).
   - Hãy dành sự táo bạo cho điểm nhấn này, giữ các thành phần xung quanh kỷ luật, tối giản và ngăn nắp.
3. **Cấu trúc mang Thông tin (Structure is Information)**:
   - Nhãn, đường phân cách, các con số chỉ dẫn phải chứa đựng ý nghĩa thực sự.
   - **Không dùng số thứ tự trang trí (01 / 02 / 03)** trừ khi nội dung thực sự là một quy trình tuần tự có thứ tự trước sau.
4. **Tiết chế & Tự phê bình (Restraint & Self-Critique)**:
   - Áp dụng nguyên tắc Chanel trong thiết kế: *Trước khi hoàn tất giao diện, hãy kiểm tra lại và loại bỏ 1 chi tiết trang trí không thực sự phục vụ nghiệp vụ*.
   - Chất lượng nền tảng là bắt buộc: Responsive trên mọi màn hình, trạng thái focus rõ ràng cho bàn phím, tôn trọng thiết lập giảm chuyển động (reduced motion).
5. **Chuyển động có Mục đích (Motion with Purpose)**:
   - Sử dụng micro-interactions mượt mà để phản hồi hành động người dùng (`0.15s – 0.24s`). Tránh over-animation làm giao diện có cảm giác rườm rà hoặc "AI-generated".

### 1.2 Quy trình Thiết kế 2 Bước (2-Pass Design Workflow)
Trước khi viết mã HTML/CSS cho một view mới hoặc nâng cấp UI:

- **Pass 1: Lập Kế hoạch Thiết kế (Design Plan)**:
  - **Token System**: Xác định màu sắc (4–6 mã HEX từ hệ thống CRM), font chữ, layout wireframe.
  - **Signature Element**: Chọn 1 chi tiết nhận diện độc đáo cho trang này.
- **Pass 2: Phê bình & Tránh Các Dạng Default của AI (Anti-AI Defaults Self-Critique)**:
  - Tự kiểm tra để tránh 3 dạng giao diện rập khuôn AI phổ biến:
    1. Background màu kem (`#F4F1EA`) + font serif + accent màu terracotta.
    2. Background gần đen + accent màu xanh acid/đỏ tươi.
    3. Broadsheet/Newspaper với đường kẻ hairline mỏng nét, zero border-radius.
  - Nếu bất kỳ thành phần nào mang cảm giác template generic, hãy chỉnh sửa lại để phù hợp với ngôn ngữ thiết kế CRM của `portal_18`.

---

## 2. Quy tắc Viết Nội dung & Microcopy trong UI (Writing in Design)

Nội dung chữ trong UI là **vật liệu thiết kế**, không phải chi tiết trang trí.

1. **Viết từ góc nhìn người dùng**:
   - Đặt tên dựa trên những gì người dùng điều khiển và nhận biết (vd: "Cấu hình thông báo" thay vì "Webhook config").
   - Mô tả bằng thuật ngữ đơn giản, dễ hiểu thay vì sử dụng ngôn từ kỹ thuật hệ thống.
2. **Sử dụng Active Voice (Động từ chủ động)**:
   - Nút bấm hành động cụ thể: `Lưu thay đổi`, `Tạo báo giá`, `Gửi email` (tránh nút ghi `Gửi` chung chung hoặc `Submit`).
   - Nhất quán thuật ngữ từ nút bấm tới thông báo toast: Nút `Xuất bản` sinh ra toast `Đã xuất bản thành công`.
3. **Xử lý Trạng thái Lỗi & Empty State có tính Định hướng**:
   - **Thông báo lỗi**: Giải thích rõ điều gì chưa đúng và hướng dẫn cụ thể cách khắc phục trong giọng văn hệ thống chuyên nghiệp. Không dùng câu từ mơ hồ.
   - **Trạng thái rỗng (Empty State)**: Đừng chỉ hiển thị "Không có dữ liệu". Hãy kết hợp icon minh họa + câu hướng dẫn + nút hành động (Call-to-action) rõ ràng (vd: *"Chưa có báo giá nào được tạo. [Tạo báo giá ngay]"*).

---

## 3. Nền tảng kỹ thuật (Tech Foundation)

| Thành phần          | Giá trị hiện tại                                         |
| ------------------- | -------------------------------------------------------- |
| CSS Framework       | Bootstrap 3 (đã custom nặng qua `bs-overides.css`)      |
| Grid System         | Bootstrap grid 12 cột + CSS Grid cho module mới          |
| Icon Library        | Font Awesome 4 + Glyphicons Halflings                    |
| Theme Engine        | Module `theme_style` — inject CSS qua hook `app_admin_head` |
| Build Tool          | Grunt (xem `Gruntfile.js`)                                |

### Quy tắc chung

- **Không sửa file core** (`assets/css/style.css`, `bs-overides.css`) trừ khi được yêu cầu rõ ràng. Ưu tiên viết CSS mới trong thư mục `modules/<tên>/assets/css/`.
- CSS mới **PHẢI** sử dụng CSS Custom Properties (variables) để dễ bảo trì.
- Sử dụng **BEM-like naming** với prefix module (vd: `sp-`, `tech-`, `pr-`) để tránh xung đột với core CSS.
- Luôn đặt `box-sizing: border-box` cho container gốc module.

---

## 4. Bảng màu (Color Palette)

### 4.1 Màu hệ thống gốc (System Colors)

Các giá trị dưới đây được trích xuất trực tiếp từ `style.css` và `bs-overides.css`. **KHÔNG** thay thế bằng giá trị khác.

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

### 4.2 Màu trạng thái CRM mở rộng (CRM Status Colors)

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

### 4.3 Quy tắc sử dụng màu

1. **KHÔNG dùng màu thuần** (`red`, `blue`, `green`). Luôn dùng mã hex từ bảng trên hoặc CSS variable tương ứng.
2. **Gradient header**: Luôn dùng `linear-gradient(to right, var(--crm-header) 0%, var(--crm-header-mid) 60%, var(--crm-header-end) 100%)`.
3. **Trạng thái hover**: Darken 10-15% so với màu gốc, đã định nghĩa sẵn trong các biến `*-hover`.
4. **Background tối cho overlay**: `rgba(50, 58, 69, 0.48)` (đã dùng trong drawer backdrop).
5. **Focus ring** cho input: Border color `#03a9f4` (crm-info).

---

## 5. Typography (Kiểu chữ)

### 5.1 Font Stack

```css
/* Font chính — dùng cho toàn bộ ứng dụng */
font-family: "Roboto", "Helvetica Neue", Helvetica, Arial, sans-serif;

/* Font phụ — dùng cho badge, indicator nhỏ */
font-family: Verdana, serif;

/* Font code/mono */
font-family: Consolas, monospace;
```

> **Quy tắc**: Mọi component mới PHẢI kế thừa font từ `body`. Chỉ khai báo `font-family` nếu cần font khác (mono cho code block).

### 5.2 Font Size Scale

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

### 5.3 Font Weight & Line Height

| Token    | Giá trị | Sử dụng                                           |
| -------- | ------- | -------------------------------------------------- |
| Regular  | `400`   | Body text, heading h3/h4, badge                    |
| Medium   | `500`   | Label, `<b>`/`<strong>`, panel heading, form label  |
| SemiBold | `600`   | Button action, staff name active                   |
| Bold     | `700`   | Page title, card value, table header, KPI           |
| ExtraBold| `800`   | Table column header (uppercase)                     |

- Body text: `1.42857` (Bootstrap default)
- Heading / title: `1.35`
- Table cell: `1.3 – 1.45`
- KPI big number: `1.25`

---

## 6. Spacing & Layout

### 6.1 Spacing Scale

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

### 6.2 Layout Grid & Container

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

- `#wrapper` padding-left: `220px` (desktop sidebar) / `0` (mobile).
- `.content` padding: `20px`.
- Header height: `63px`.
- Sidebar width: `220px`.

---

## 7. Border, Radius & Shadow (Elevation)

### 7.1 Border Radius Scale

| Token         | Giá trị | Sử dụng                                        |
| ------------- | ------- | ----------------------------------------------- |
| `--radius-xs` | `2px`   | Tag nhỏ, indicator                               |
| `--radius-sm` | `3-4px` | Panel body, form control, tag, badge, status tag |
| `--radius-md` | `5-6px` | Button, modal content, card, staff trigger       |
| `--radius-lg` | `8px`   | Dashboard overview card, summary card, log card  |
| `--radius-xl` | `16px`  | Chip-circle                                      |
| `--radius-pill`| `50px` | Pill badge, header active link                   |
| `--radius-circle`| `50%`| Avatar, rank badge                               |

### 7.2 Border & Shadow Elevation

```css
/* Standard card/panel border */
border: 1px solid var(--crm-border);          /* #e4e8f1 — module mới */
border: 1px solid var(--crm-border-panel);     /* #dce1ef — panel_s gốc */
border-input: 1px solid var(--crm-border-input); /* #d6d6d6 */

/* Elevation Shadows */
box-shadow: none;                                                 /* Level 0 */
box-shadow: 0 1px 1px rgba(0, 0, 0, 0.03);                        /* Level 1 - Summary Card */
box-shadow: 0 1px 15px 1px rgba(90, 90, 90, 0.08);                /* Level 2 - Panel */
box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05), 0 2px 4px 0 rgba(0, 0, 0, 0.2); /* Level 3 - Modal */
box-shadow: -10px 0 30px rgba(50, 58, 69, 0.2);                   /* Level 4 - Drawer */
```

---

## 8. Component Patterns

### 8.1 Buttons

```css
.btn {
    text-transform: uppercase;
    font-size: 13.5px;
    padding: 5px 10px;
    border: 0;
    outline-offset: 0;
    transition: all 0.15s ease-in-out;
}

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
```

### 8.2 Cards / Panels

```css
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

.summary-card {
    position: relative;
    padding: 17px 18px 15px;
    border: 1px solid var(--crm-border);
    border-radius: 8px;
    background: var(--crm-bg-white);
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.03);
}

.summary-card::before {
    position: absolute;
    top: 0; right: 0; left: 0;
    height: 3px;
    content: '';
    background: var(--crm-muted);
}
```

### 8.3 Forms & Tables

```css
input, select, textarea {
    padding: 5px 10px;
    border: 1px solid #d6d6d6;
    height: 36px;
    font-size: 14px;
    transition: border-color 0.2s cubic-bezier(0.645, 0.045, 0.355, 1);
}

input:focus, select:focus, textarea:focus {
    border-color: #03a9f4;
    outline: 0;
}

th {
    height: 46px;
    padding: 10px 12px;
    background: var(--crm-bg-soft);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

td {
    height: 76px;
    padding: 11px 12px;
    border-bottom: 1px solid var(--crm-border);
    vertical-align: middle;
}
```

### 8.4 Drawer & Modal

```css
.drawer__backdrop {
    background: rgba(50, 58, 69, 0.48);
    transition: opacity 0.2s ease;
}

.drawer__panel {
    width: min(760px, 92vw);
    background: #f7f8fa;
    box-shadow: -10px 0 30px rgba(50, 58, 69, 0.2);
    transform: translateX(100%);
    transition: transform 0.24s ease;
}

.modal-header {
    background: linear-gradient(to right, var(--crm-modal-header-start) 0%, var(--crm-modal-header-mid) 37%, var(--crm-modal-header-end) 100%);
    border-radius: 6px 6px 0 0;
    color: #fff;
    padding: 18px;
}
```

### 8.5 Status Badges, Avatars & Progress

```css
.avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 1px #d5dde5;
}

.status-tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 7px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
}

.progress {
    height: 5px;
    border-radius: 3px;
    background: #e4e8f1;
}
```

---

## 9. Animations & Transitions

```css
transition: all 0.15s ease-in-out;                                /* Micro-interactions */
transition: border-color 0.2s cubic-bezier(0.645, 0.045, 0.355, 1); /* Form Input focus */
transition: transform 0.24s ease;                                 /* Drawer Slide */
```

- **Quy tắc**: Không dùng animation > `0.4s` cho tác vụ UI. Tránh animation rườm rà gây mất tập trung. On mobile (`≤767px`), dùng `transition: none;` cho drawer/overlay để tối ưu hiệu năng.

---

## 10. Responsive Breakpoints

```css
@media (max-width: 767px)  { /* Phone */       }
@media (min-width: 768px)  { /* Tablet */      }
@media (min-width: 992px)  { /* Desktop SM */  }
@media (min-width: 1200px) { /* Desktop LG */  }
```

---

## 11. Navigation & Header

```css
#header {
    background: linear-gradient(to right, #415165, #51647c 26%, #51647c 73%, #4f5d7a);
    height: 63px;
}

#side-menu { background: #626f80; }
#side-menu li.active > a { background: #e3e8ee; color: #323a45; }
```

---

## 12. Accessibility & UX Checklist

Mọi component/view mới PHẢI đáp ứng các mục kiểm tra sau:

- [ ] **Contrast ratio** ≥ 4.5:1 cho text trên background.
- [ ] **Focus visible**: Interactive elements có viền focus rõ ràng.
- [ ] **Keyboard navigation**: Hỗ trợ Tab, Enter, Space đầy đủ.
- [ ] **Touch target**: Tối thiểu `38×38px` trên mobile.
- [ ] **Loading state**: Hiển thị skeleton/spinner khi tải dữ liệu.
- [ ] **Empty state**: Hiển thị icon + lời mời gọi hành động (Call-to-action) rõ ràng.
- [ ] **Error state**: Viền đỏ (`--crm-danger`) + thông báo khắc phục cụ thể.
- [ ] **Signature Element**: Đã chọn 1 chi tiết thiết kế nhận diện đặc trưng cho view.
- [ ] **Microcopy active voice**: Đặt tên nút và câu thông báo bằng động từ chủ động.
- [ ] **Restraint check**: Đã rà soát và loại bỏ các chi tiết trang trí thừa.

---

## 13. CRM-Specific Patterns

### 13.1 Dashboard KPI Card
```
┌─────────────────────────────┐
│▀▀▀▀▀▀▀▀▀ (3px accent bar) ▀│  ← màu theo trạng thái
│  [Label]         [Period]   │
│                             │
│  28,500,000đ                │  ← --fs-stat, font-weight 700
│  ████████░░░░  75%          │  ← progress bar
└─────────────────────────────┘
```

### 13.2 Kanban Pipeline Card
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

### 13.3 Action Feed Item & Leaderboard Row
```
┌ 3px danger border ───────────────────┐
│ [Avatar] Staff Name                  │
│ Task: Gọi điện xác nhận hợp đồng    │
│ [Gọi ngay] [Bỏ qua]                 │
└──────────────────────────────────────┘

│ 🥇 │ [Av] Tên NV    │ Pipeline │ Doanh thu │ Tỷ lệ   │ Cơ hội │
```

### 13.4 Quy tắc Sử dụng Icon Đồng bộ & Thông minh (Smart Icon System)

Bộ icon chính của ứng dụng là **Font Awesome (v4.7.0)**. Khi thiết kế hoặc chỉnh sửa bất kỳ UI/View/Component nào, Agent PHẢI tuân thủ bộ quy tắc chọn và định dạng icon dưới đây:

#### A. Ánh xạ Icon theo Ngữ cảnh Nghiệp vụ (Semantic Mapping)
Mỗi đối tượng hoặc hành động nghiệp vụ CHỈ ĐƯỢC DÙNG đúng 1 đại diện icon cố định trên toàn bộ hệ thống:

| Đối tượng Nghiệp vụ | Icon Class chuẩn | Mô tả & Ngữ cảnh sử dụng |
| :--- | :--- | :--- |
| **Cơ hội / Deal** | `fa-briefcase` hoặc `fa-folder-open-o` | Danh sách cơ hội, tab deal, danh mục deal đang mở |
| **Báo giá / Estimate** | `fa-file-text-o` | Danh sách báo giá, tạo/sửa báo giá |
| **Doanh thu / Giá trị** | `fa-line-chart` / `fa-money` | Thẻ chỉ số doanh thu, tiền tệ, KPI tài chính |
| **Cần theo dõi / Nhắc nhở** | `fa-bell-o` | Thẻ theo dõi, thông báo nhắc nhở, rủi ro cần xử lý |
| **Nhật ký / Activity Log** | `fa-comments-o` / `fa-history` | Activity feed, lịch sử phản hồi, mốc thời gian |
| **Xếp hạng / Leaderboard** | `fa-trophy` | Bảng vinh danh, xếp hạng hiệu suất nhân viên |
| **Khách hàng / Công ty** | `fa-building-o` / `fa-user` | Doanh nghiệp, người đại diện liên hệ |
| **Thời gian / Hạn chót** | `fa-clock-o` / `fa-calendar` | Mốc ngày deal, lịch nhắc, thời gian phản hồi |

#### B. Phân loại Icon theo Trạng thái & Thị giác (Status & Visual Hierarchy)
- **Đang chờ / Pending**: `<i class="fa fa-clock-o text-warning" aria-hidden="true"></i>`
- **Thành công / Hoàn thành / Won**: `<i class="fa fa-check-circle text-success" aria-hidden="true"></i>`
- **Cảnh báo / Rủi ro / Risk**: `<i class="fa fa-exclamation-triangle text-danger" aria-hidden="true"></i>`
- **Chỉ thông báo / Informational**: `<i class="fa fa-info-circle text-info" aria-hidden="true"></i>`

#### C. Quy chuẩn Kỹ thuật & Accessibility (Technical Standards)
1. **Accessibility**: Luôn thêm `aria-hidden="true"` cho icon trang trí đi kèm chữ:
   `<i class="fa fa-refresh" aria-hidden="true"></i>`
2. **Căn lề danh sách (Fixed Width)**: Khi hiển thị icon trong danh sách hàng dọc (Vertical menu / List), luôn thêm class `fa-fw` để văn bản bên phải thẳng hàng tuyệt đối.
3. **Kích thước Icon**:
   - Trong Button / Badge: `12px – 14px`
   - Trong Tab / Card Header: `16px`
   - Trong Stat Card / Empty State: `24px – 32px`

---

## 14. Do's and Don'ts

### ✅ DO

- Thực hiện quy trình thiết kế 2 bước (Pass 1: Token & Signature, Pass 2: Self-critique anti-defaults).
- Xác định 1 điểm nhấn thiết kế nhận diện duy nhất (Signature Element) cho mỗi trang chính.
- Sử dụng CSS Custom Properties (variables) cho mọi giá trị màu, spacing.
- Đặt tên nút và thông báo bằng câu chủ động (Active voice).
- Thiết kế Empty state hấp dẫn có nút hành động (Call-to-action).
- Dùng `gap` thay vì `margin` trong layout container.
- Test trên ≥ 3 breakpoints trước khi hoàn tất.

### ❌ DON'T

- ❌ Rập khuôn giao diện dạng AI template vô hồn.
- ❌ Dùng `Lorem Ipsum` hoặc dữ liệu mẫu không có thật.
- ❌ Đặt tên nút mơ hồ (`Submit`, `OK`, `Thực hiện`).
- ❌ Dùng số thứ tự trang trí (`01`, `02`, `03`) khi nội dung không phải là quy trình tuần tự.
- ❌ Nhồi nhét quá nhiều hiệu ứng animation hoặc trang trí rườm rà.
- ❌ Hardcode màu trực tiếp — luôn dùng CSS variable.
- ❌ Dùng `!important` trừ khi override core CSS bắt buộc.
- ❌ Xóa hoặc ghi đè `bs-overides.css` / `style.css`.

---

## 15. Cấu trúc File & Đăng ký Asset Module

```
modules/<module_name>/
├── assets/
│   ├── css/
│   │   └── <module>.css          ← CSS riêng, dùng CSS variables
│   └── js/
│       └── <module>.js
├── views/
│   ├── dashboard.php
│   └── partials/
└── <module>.php                  ← register CSS/JS qua hooks
```

**Hook đăng ký CSS**:
```php
hooks()->add_action('app_admin_head', function() {
    echo '<link rel="stylesheet" href="' . module_dir_url(MODULE_NAME, 'assets/css/module.css') . '">';
});
```

---

## 16. Khi nào KHÔNG áp dụng skill này

- Sửa lỗi logic PHP / database — không liên quan UI.
- Viết migration, model, hoặc API endpoint — không có giao diện.
- Chỉnh sửa language file — không ảnh hưởng styling.
- Task được yêu cầu rõ ràng "không thay đổi giao diện".

---

*Skill version: 2.0.0 — Integrated Frontend Design Philosophy with Portal 18 CRM UI System on 2026-08-10.*

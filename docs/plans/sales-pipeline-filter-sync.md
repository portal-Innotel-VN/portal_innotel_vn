# Feature Plan: Sales Pipeline filter synchronization

## Metadata

- plan_id: `sales-pipeline-filter-sync`
- plan_version: `1`
- plan_status: `READY_FOR_QC`
- qc_status: `PENDING`
- planner: `Codex`
- qc_reviewer: `Gemini 3.6 Flash (High)`
- implementer: `Gemini/Antigravity`
- updated_at: `2026-08-10`

## Goal

Dong bo toan bo filter cua trang `/admin/sales_pipeline` giua List View va Kanban View de cung mot bo filter luon tra ve cung tap deal khi chuyen view, tai them Kanban, tim kiem, Enter, Clear va chon "Tat ca cac nam".

## Business context

Nguoi dung van hanh Sales Pipeline can tin rang List View va Kanban View chi la hai cach nhin cua cung mot tap du lieu. Cac filter can giu dong nhat: `search`, `quarter`, `year`, `staff_id`, `contract_signed`, `invoice_issued`, `sort`, `sort_type`.

## Basecode evidence

| File / symbol | Hien trang da xac minh | Anh huong |
|---|---|---|
| `modules/sales_pipeline/views/manage.php` switch link | Link chuyen view render bang PHP: `admin_url('sales_pipeline/switch_kanban/' . $switch_kanban . $query_string)`, khong co id rieng cho nut switch. | Can them selector namespaced hien huu/ro rang cho JS cap nhat `href`; khong duoc gia dinh `#btn_switch_kanban`. |
| `modules/sales_pipeline/views/manage.php` `#filter_year` | Select nam chi render cac nam gan day va khong co option rong. | Khong co cach chon tat ca cac nam; can them option `value=""` bang language key. |
| `modules/sales_pipeline/views/manage.php` `fetchDealsAjax()` | Lay `quarter/year/staff/per_page/search/document`, cap nhat URL cho List, gui document thanh mot trong hai param `contract_signed` hoac `invoice_issued`. | Co nen tai su dung logic build filter de dong bo URL va link switch cho ca List/Kanban. |
| `modules/sales_pipeline/views/manage.php` `pipeline_kanban()` | Gui POST day du filter toi endpoint Kanban, nhung khi input search debounce chi cap nhat URL moi `search`; success chi `loadArea.html(response.kanban)`. | URL/link switch khong dong bo day du trong Kanban; summary khong cap nhat sau Kanban response. |
| `modules/sales_pipeline/views/manage.php` `pipeline_load_more()` | Gui `search`, `sort`, `sort_type`, `quarter`, `year`, `staff_id`; khong gui `contract_signed`/`invoice_issued`. | Nut "Tai them" co the mat filter chung tu. |
| `modules/sales_pipeline/views/manage.php` search handlers | Input debounce co `clearTimeout`; Enter va Clear request ngay nhung khong `clearTimeout(searchTimer)`. | Mot thao tac Enter/Clear co the bi duplicate request do debounce cu chay sau do. |
| `modules/sales_pipeline/views/manage.php` `renderDealTable(deals, isAdmin, currentUserId, offset)` | Bien `isAdmin` dung chung cho cot action, nut xem, nut xoa va quyen sua gia nhap. | User co `view_deal_details` co the thay nut xoa sau AJAX vi flag backend gom quyen. |
| `modules/sales_pipeline/controllers/Sales_pipeline::index()` | `year` tu GET rong/null bi default `date('Y')`; `where` them `YEAR(deal_date)` neu co year. | Trang dau khong ho tro "tat ca cac nam". |
| `modules/sales_pipeline/controllers/Sales_pipeline::ajax_search()` | `year` tu GET rong/null bi default `date('Y')`; JSON tra `is_admin` la gom `is_admin || view_deal_details || delete`. | AJAX List khong ho tro "tat ca cac nam"; permission UI contract bi sai. |
| `modules/sales_pipeline/controllers/Sales_pipeline::kanban()` | Nhan filter POST va render `kan-ban`; response chi co `kanban`. | Can tinh `summary` cung semantics List va tra kem JSON. |
| `modules/sales_pipeline/controllers/Sales_pipeline::kanban_load_more()` | Tao `$sort` co `contract_signed`/`invoice_issued` va model da doc hai param. | Backend load-more da co duong nhan, can dam bao frontend gui dung va card link giu query. |
| `modules/sales_pipeline/models/Sales_pipeline_model::get_summary()` | Neu `$year === null` thi ep `date('Y')`, va luon them `$base_where['YEAR(deal_date)'] = $year`. | Summary lech voi yeu cau year rong la khong loc nam; can chi filter nam khi year khac rong/null. |
| `modules/sales_pipeline/models/Sales_pipeline_model::do_kanban_query()` | Ap dung search, quarter/year/staff_id/document va view_own scope; sort ho tro `datecreated`, `deal_date`, `deal_value`. | Can giu scope hien co; neu `actual_profit` sort da co UI link nhung model chua sort, chi sua neu trong pham vi dong bo sort can thiet va khong doi semantics ngoai filter. |
| `modules/sales_pipeline/language/*/sales_pipeline_lang.php` | Co key quarter/document, chua thay key "all years". | Them English/Vietnamese key, khong hardcode text UI. |

## Scope

### In scope

- Chi trang `/admin/sales_pipeline`.
- Chi List View, Kanban View, AJAX List, Kanban render va Kanban load-more.
- Dong bo URL va switch link theo filter hien tai.
- Summary Kanban dung cung filter/search/staff scope voi List.
- Filter nam rong = tat ca cac nam.
- Sua duplicate AJAX cua Enter/Clear.
- Sua UI permission contract sau AJAX bang cac flag doc lap.
- Language key English/Vietnamese cho option "All years" / "Tat ca cac nam".

### Non-goals

- Khong sua Dashboard, dashboard drawer, `_dashboard_staff_pipeline`, staff member panel, activity log, `/admin/staff/member/{id}`.
- Khong migration, khong ghi data CRM.
- Khong sua `application/`, `system/`, vendor/dependency.
- Khong thay doi layout/meaning cua summary cards.
- Khong mo rong permission thuc te cua controller/backend.

## Required behavior

1. Tao helper JS trong `manage.php` de doc filter hien tai mot lan:
   - `search` tu `#pipeline_search`/`input[name="search"]`.
   - `quarter`, `year`, `staff_id`, `sort`, `sort_type`.
   - `contract_signed`/`invoice_issued` tu `#filter_document`, moi trang thai chi set mot loai; khi "all documents" thi xoa ca hai.
2. Tao helper JS cap nhat URL hien tai va `href` nut switch view tu cung object filter:
   - Xoa param rong/null/undefined.
   - Giu `per_page/page` cho List neu phu hop voi List AJAX; khi switch view phai giu cac filter bat buoc va `sort/sort_type`, khong bat buoc giu `per_page`.
   - Nut switch can dung selector thuc te. Neu them moi, dung class/id namespaced vi du `js-sales-pipeline-switch-view` tren anchor hien co.
3. Moi lan `fetchDealsAjax()`, `pipeline_kanban()`, `pipeline_kanban_sort()`, input debounce, Enter, Clear, filter onchange deu cap nhat URL va switch link bang helper chung.
4. `Sales_pipeline::kanban()` tra JSON co `kanban` va `summary`; `pipeline_kanban()` goi `updateSummary(response.summary)` hoac tuong thich voi response standard neu duoc chon.
5. `pipeline_load_more()` gui them `contract_signed`/`invoice_issued` theo `#filter_document`, khong gui dong thoi hai dieu kien mau thuan.
6. `index()` va `ajax_search()` doc `year` sao cho GET missing/null/empty string deu la khong loc nam. Khong ep default `date('Y')`.
7. `Sales_pipeline_model::get_summary()` chi them dieu kien `YEAR(deal_date)` khi `$year !== null && $year !== ''`.
8. PHP initial render, AJAX List va Kanban cung ap dung `quarter`, `year`, `staff_id`, `search`, `contract_signed`, `invoice_issued` nhu nhau va giu view/view_own scope hien co.
9. AJAX permission flags:
   - Backend tra `can_view_deal_details`, `can_delete_deal`, `can_edit_cost_price` doc lap.
   - `renderDealTable()` doi signature de dung object permission hoac cac flag doc lap.
   - Cot action chi render neu `can_view_deal_details || can_delete_deal`.
   - Nut xem chi render neu `can_view_deal_details`.
   - Nut xoa chi render neu `can_delete_deal`.
   - Sua gia nhap sau AJAX dung `can_edit_cost_price` ket hop ownership hien co neu flag la quyen global/admin; khong cho user khong lien quan thay nut sua.

## Data, permission and security

- Giu permission gate hien co: `view` hoac `view_own` moi duoc xem endpoint.
- Giu rule scope: neu khong co `view` global thi controller/model ep `staff_id = get_staff_user_id()` hoac model `do_kanban_query()` tu ep staff hien tai.
- Khong gui CRM data ra ngoai. Antigravity/QC chi nhan plan va tu doc repo local; khong dua noi dung deal/customer thuc te vao prompt.
- Dung CodeIgniter Query Builder, `db_prefix()` khi tao/sua dieu kien query.
- Escape text trong view nhu hien co; khong them hardcoded UI text.
- Khong them migration hay write path ghi data.

## Changes by file

1. `modules/sales_pipeline/views/manage.php`
   - Them class/id namespaced cho anchor switch view hien co, vi du `class="... js-sales-pipeline-switch-view"` va optional `data-switch-kanban`.
   - Them option `value=""` dau tien trong `#filter_year`: `_l('sales_pipeline_all_years')`; selected khi `$current_year === null || $current_year === ''`.
   - Them JS helpers:
     - `getSalesPipelineFilterParams(includeListPagination)`.
     - `applyDocumentFilterParams(params)`.
     - `syncSalesPipelineUrl(params)`.
     - `syncSalesPipelineSwitchUrl(params)`.
   - Refactor `fetchDealsAjax()`, `pipeline_kanban()`, `pipeline_load_more()` dung helper chung.
   - `pipeline_kanban()` success cap nhat summary.
   - Enter/Clear handlers goi `clearTimeout(searchTimer)` truoc request ngay.
   - `renderDealTable()` dung permission flags doc lap thay `isAdmin` gom quyen.
2. `modules/sales_pipeline/controllers/Sales_pipeline.php`
   - `index()`: year default la `null`/`''`, khong `date('Y')`; chi where nam khi khac rong.
   - `ajax_search()`: year default la `null`/`''`; JSON tra flag doc lap:
     - `can_view_deal_details = is_admin() || has_permission('sales_pipeline', '', 'view_deal_details')`.
     - `can_delete_deal = is_admin() || has_permission('sales_pipeline', '', 'delete')`.
     - `can_edit_cost_price = is_admin()` neu UI initial render chi cho admin/global? Can giu khop PHP initial: nut cost price hien render neu admin hoac deal owner/importer. Vi flag toi thieu la global/admin, frontend van ket hop owner/importer tung deal.
   - `kanban()`: tinh `$summary = get_summary($quarter, $year, $staff_id_effective, $search, $contract_signed, $invoice_issued)` sau khi ap view_own staff scope, tra kem JSON.
   - `kanban_load_more()`: neu can, chuan hoa document params rong de tranh dieu kien mau thuan va truyen `query_string` vao card view de link chi tiet giu query.
3. `modules/sales_pipeline/models/Sales_pipeline_model.php`
   - `get_summary()`: bo default `$year = date('Y')`; chi them `YEAR(deal_date)` khi year co gia tri.
   - Giu `do_kanban_query()` scope/view_own va document filters; neu sua sort `actual_profit` thi chi them order_by expression an toan tu select hien co, khong dung input raw.
4. `modules/sales_pipeline/language/english/sales_pipeline_lang.php`
   - Them `$lang['sales_pipeline_all_years'] = 'All years';`.
5. `modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php`
   - Them `$lang['sales_pipeline_all_years'] = 'Tat ca cac nam';` hoac co dau UTF-8 neu file dang dung co dau.
6. `docs/plans/sales-pipeline-filter-sync.qc.md`
   - Gemini ghi delta QC, khong sua source trong phase QC.
7. `docs/plans/sales-pipeline-filter-sync.implementation.md`
   - Gemini ghi bao cao sau khi implement.

## Failure states and rollback

- Neu QC phat hien file/function khac source thuc te, dung implement, cap nhat plan, tang `plan_version`.
- Neu diff cham ngoai scope Dashboard/staff panel/core, revert chi cac hunk do cua implementer sau khi xac minh khong phai thay doi co san cua user.
- Neu lint PHP fail, sua syntax truoc khi verify acceptance.
- Neu AJAX response schema thay doi lam List/Kanban loi, can giu backward compatibility trong JS bang cach doc `response.data || response` nhu hien co.

## Acceptance criteria

- [ ] Cung mot filter tao ra cung tap deal o List va Kanban.
- [ ] URL va link chuyen List/Kanban giu day du filter hien tai: `search`, `quarter`, `year`, `staff_id`, `contract_signed`, `invoice_issued`, `sort`, `sort_type`.
- [ ] Kanban summary cap nhat theo filter va khop List summary.
- [ ] "Tai them" Kanban giu dung filter Chung tu va khong gui dieu kien document mau thuan.
- [ ] Chon "Tat ca cac nam" tra deal cua moi nam o initial List, AJAX List va Kanban.
- [ ] Enter va Clear tao toi da mot request du lieu cho moi thao tac.
- [ ] Sau AJAX, nut Xem/Xoa/Sua gia nhap khop quyen thuc te va initial PHP render.
- [ ] User chi co `view_own` khong thay du lieu staff khac.
- [ ] User co `view_deal_details` nhung khong co `delete` khong thay nut Xoa sau AJAX.
- [ ] Desktop/mobile khong co console error do JS thay doi.
- [ ] PHP lint va `git diff --check` PASS.

## Validation commands

```bash
git status --short
php -l modules/sales_pipeline/controllers/Sales_pipeline.php
php -l modules/sales_pipeline/models/Sales_pipeline_model.php
php -l modules/sales_pipeline/views/manage.php
php -l modules/sales_pipeline/views/kan-ban.php
php -l modules/sales_pipeline/views/_kanban_card.php
php -l modules/sales_pipeline/language/english/sales_pipeline_lang.php
php -l modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php
git diff --check
git diff -- modules/sales_pipeline/views/manage.php modules/sales_pipeline/controllers/Sales_pipeline.php modules/sales_pipeline/models/Sales_pipeline_model.php modules/sales_pipeline/language/english/sales_pipeline_lang.php modules/sales_pipeline/language/vietnamese/sales_pipeline_lang.php docs/plans/sales-pipeline-filter-sync.md docs/plans/sales-pipeline-filter-sync.qc.md docs/plans/sales-pipeline-filter-sync.implementation.md
```

Manual/browser validation at `http://localhost:8000/admin/sales_pipeline`:

- List -> Kanban -> List with all filter groups.
- Contract/invoice document filters plus Kanban "Tai them".
- Empty year "Tat ca cac nam".
- Search typing debounce, Enter, Clear with Network panel verifying max one data request for Enter/Clear.
- Permission scenarios: `view_own`; `view_deal_details` without `delete`.
- Desktop and mobile viewport; console error count 0.

## Open questions

- Khong co ambiguity nghiep vu can hoi user. Can Gemini/QC xac minh them source thuc te ve permission sua gia nhap de giu khop initial PHP render.

## QC history

| Plan version | QC result | Delta incorporated |
|---|---|---|
| 1 | PENDING | Initial plan from basecode evidence. |

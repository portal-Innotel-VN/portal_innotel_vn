import sys
import json
import datetime

# =====================================================================
# parse_excel.py — Đọc file Excel Template chuẩn Innotel
#
# Cấu trúc Template (cố định):
#   Dòng 1-4  : Tiêu đề & hướng dẫn  → BỎ QUA
#   Dòng 5-6  : Dữ liệu ví dụ mẫu    → BỎ QUA
#   Dòng 7-56 : Vùng nhập liệu thực  → ĐỌC (index 6-55)
#
# Mapping 8 cột (A→H, index 0-7):
#   0: Ngày tạo (Date DD/MM/YYYY)
#   1: Tên công ty KH (String)
#   2: Mô tả sản phẩm/DV (String, nullable)
#   3: Doanh số VNĐ (Integer)
#   4: % Lợi nhuận (Float, nullable)
#   5: Lợi nhuận VNĐ (Float, nullable — công thức Excel, đọc calculated value)
#   6: Trạng thái (String — dropdown)
#   7: Ghi chú (String, nullable)
# =====================================================================

DATA_START_ROW = 6   # index 0-based → dòng 7 trong Excel
DATA_END_ROW   = 55  # index 0-based → dòng 56 trong Excel
NUM_COLS       = 8   # Số cột đọc (A đến H)


def is_row_empty(row_data):
    """Bỏ qua dòng nếu Cột A (Ngày) và Cột B (Tên KH) đều rỗng."""
    col_a = str(row_data[0]).strip() if len(row_data) > 0 else ''
    col_b = str(row_data[1]).strip() if len(row_data) > 1 else ''
    return col_a == '' and col_b == ''


def normalize_date(value):
    """
    Chuẩn hóa giá trị ngày sang chuỗi YYYY-MM-DD.
    Xử lý các trường hợp: datetime object, string DD/MM/YYYY, serial number.
    """
    if value is None or str(value).strip() == '':
        return ''

    # openpyxl trả về datetime object khi data_only=True
    if isinstance(value, (datetime.datetime, datetime.date)):
        return value.strftime('%Y-%m-%d')

    val_str = str(value).strip()

    # Dạng DD/MM/YYYY
    if '/' in val_str:
        parts = val_str.split('/')
        if len(parts) == 3:
            try:
                d, m, y = parts
                return f"{int(y):04d}-{int(m):02d}-{int(d):02d}"
            except ValueError:
                pass

    # Dạng YYYY-MM-DD (đã chuẩn)
    if '-' in val_str and len(val_str) >= 10:
        return val_str[:10]

    # Dạng serial number Excel (số float/int)
    try:
        serial = float(val_str)
        if serial > 1:
            # Excel serial date: ngày 1 = 1900-01-01
            dt = datetime.datetime(1899, 12, 30) + datetime.timedelta(days=serial)
            return dt.strftime('%Y-%m-%d')
    except ValueError:
        pass

    return val_str


def normalize_cell(value):
    """Chuẩn hóa giá trị ô thông thường sang string hoặc số."""
    if value is None:
        return ''
    if isinstance(value, float):
        # Tránh hiển thị 150000000.0 → trả nguyên float để PHP tự parse
        return value
    if isinstance(value, int):
        return value
    return str(value).strip()


def parse_xlsx(file_path):
    """Đọc file .xlsx — Template chuẩn Innotel."""
    import openpyxl
    wb = openpyxl.load_workbook(file_path, data_only=True)

    # Luôn lấy sheet đầu tiên (sheet "Báo cáo kinh doanh")
    sheet = wb.worksheets[0]

    rows = []
    max_row = min(sheet.max_row, DATA_END_ROW + 1)  # +1 vì iter_rows end inclusive

    for row_idx, row in enumerate(sheet.iter_rows(
        min_row=DATA_START_ROW + 1,  # iter_rows dùng 1-based
        max_row=max_row,
        max_col=NUM_COLS,
        values_only=True
    )):
        row_data = []
        for col_idx, cell_val in enumerate(row):
            if col_idx == 0:
                # Cột A: Ngày tạo — chuẩn hóa về YYYY-MM-DD
                row_data.append(normalize_date(cell_val))
            else:
                row_data.append(normalize_cell(cell_val))

        # Đảm bảo đủ 8 phần tử
        while len(row_data) < NUM_COLS:
            row_data.append('')

        # Bỏ qua dòng rỗng (Cột A và Cột B đều trống)
        if is_row_empty(row_data):
            continue

        rows.append(row_data)

    return rows


def parse_xls(file_path):
    """Đọc file .xls cũ (legacy) — vẫn hỗ trợ nhưng khuyến khích dùng .xlsx."""
    import xlrd
    wb = xlrd.open_workbook(file_path)
    sheet = wb.sheet_by_index(0)

    rows = []
    for r in range(DATA_START_ROW, min(sheet.nrows, DATA_END_ROW + 1)):
        row_data = []
        for c in range(min(sheet.ncols, NUM_COLS)):
            cell = sheet.cell(r, c)
            if c == 0:
                # Cột A: Ngày tạo
                if cell.ctype == xlrd.XL_CELL_DATE:
                    try:
                        dt = xlrd.xldate_as_tuple(cell.value, wb.datemode)
                        row_data.append(f"{dt[0]:04d}-{dt[1]:02d}-{dt[2]:02d}")
                    except Exception:
                        row_data.append(str(cell.value))
                elif cell.ctype == xlrd.XL_CELL_NUMBER:
                    # Serial date dạng số
                    row_data.append(normalize_date(cell.value))
                elif cell.ctype == xlrd.XL_CELL_EMPTY:
                    row_data.append('')
                else:
                    row_data.append(normalize_date(str(cell.value).strip()))
            elif cell.ctype == xlrd.XL_CELL_NUMBER:
                if cell.value == int(cell.value):
                    row_data.append(int(cell.value))
                else:
                    row_data.append(cell.value)
            elif cell.ctype == xlrd.XL_CELL_EMPTY:
                row_data.append('')
            else:
                row_data.append(str(cell.value).strip())

        # Đảm bảo đủ 8 phần tử
        while len(row_data) < NUM_COLS:
            row_data.append('')

        if is_row_empty(row_data):
            continue

        rows.append(row_data)

    return rows


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'Missing file path argument'}))
        sys.exit(1)

    file_path = sys.argv[1]
    ext = file_path.split('.')[-1].lower()

    try:
        if ext == 'xls':
            rows = parse_xls(file_path)
        else:
            rows = parse_xlsx(file_path)
        print(json.dumps(rows, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({'error': str(e)}))

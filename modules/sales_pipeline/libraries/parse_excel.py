import sys
import json
import xlrd
import openpyxl

def parse_xls(file_path):
    wb = xlrd.open_workbook(file_path)
    
    # Tìm sheet phù hợp nhất (ưu tiên tên chứa 'chi tiết' hoặc sheet có nhiều cột hơn)
    sheet = None
    for s in wb.sheets():
        if "chi tiết" in s.name.lower() or "chitiet" in s.name.lower():
            sheet = s
            break
    if not sheet and len(wb.sheets()) > 1:
        # Thử lấy sheet 1 (thường là sheet chi tiết sau sheet tổng quan)
        sheet = wb.sheet_by_index(1)
    if not sheet:
        sheet = wb.sheet_by_index(0)
        
    rows = []
    for r in range(sheet.nrows):
        row_data = []
        for c in range(sheet.ncols):
            cell = sheet.cell(r, c)
            if cell.ctype == xlrd.XL_CELL_DATE:
                try:
                    dt = xlrd.xldate_as_tuple(cell.value, wb.datemode)
                    row_data.append(f"{dt[0]}-{dt[1]:02d}-{dt[2]:02d}")
                except:
                    row_data.append(str(cell.value))
            elif cell.ctype == xlrd.XL_CELL_NUMBER:
                if cell.value == int(cell.value):
                    row_data.append(int(cell.value))
                else:
                    row_data.append(cell.value)
            elif cell.ctype == xlrd.XL_CELL_EMPTY:
                row_data.append("")
            else:
                row_data.append(str(cell.value).strip())
        rows.append(row_data)
    return rows

def parse_xlsx(file_path):
    wb = openpyxl.load_workbook(file_path, data_only=True)
    
    # Tìm active sheet hoặc sheet có chứa 'chi tiết'
    sheet = wb.active
    for name in wb.sheetnames:
        if "chi tiết" in name.lower() or "chitiet" in name.lower():
            sheet = wb[name]
            break
            
    rows = []
    for row in sheet.iter_rows(values_only=True):
        row_data = []
        for val in row:
            if val is None:
                row_data.append("")
            else:
                row_data.append(str(val).strip())
        rows.append(row_data)
    return rows

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps([]))
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
        print(json.dumps({"error": str(e)}))

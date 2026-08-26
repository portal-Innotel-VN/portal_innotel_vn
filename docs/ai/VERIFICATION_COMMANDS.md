# Verification Commands

Chạy từ root repository.

## Test module

```bash
for f in modules/sales_pipeline/tests/*_test.php; do
    php "$f"
done
```

## PHP lint

```bash
find modules/sales_pipeline -type f -name '*.php' -print0 | \
    xargs -0 -n1 php -l
```

## Whitespace/diff

```bash
git diff --check
git status --short
```

## Lưu ý báo cáo

- Ghi đúng số test thực tế.
- Nêu rõ test contract, unit, DB-backed hay manual UI.
- Không coi lint pass là bằng chứng nghiệp vụ pass.
- Nếu chưa chạy migration/UI, ghi rõ điều kiện còn thiếu.

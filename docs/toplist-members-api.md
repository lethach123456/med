# Toplists: cơ sở y tế và bác sĩ

Mỗi bài có `medical_toplists.entity_type`: `facility` (mặc định, tương thích bài cũ) hoặc `doctor`.
Thứ hạng lưu ở `medical_toplist_facilities` hoặc `medical_toplist_doctors`. Các liên kết chỉ nhận hồ sơ đã xuất bản, cùng ngôn ngữ với bài.

## Admin

- `medical_toplist_edit.php`: chọn đối tượng xếp hạng; tìm, thêm/xoá, kéo thả giống nhau cho hai loại. Lưu bài và thay liên kết trong cùng transaction.
- `medical_toplists.php`: chọn mẫu JSON / prompt bác sĩ hoặc cơ sở. Nhập tiêu đề cũng giữ loại bài cho hàng đợi AI.
- JSON có `doctors` được tự nhận diện ngay cả khi chưa có `entity_type`. Không trộn hai loại trong một bài.

Ví dụ nhập bài mới, liên kết bác sĩ sẵn có (thay ID mẫu bằng ID thực):

```json
{
  "title": "Bác sĩ chuyên khoa mắt tại Hà Nội đáng tham khảo",
  "entity_type": "doctor",
  "excerpt": "Đối chiếu chuyên môn và nơi công tác trước khi lựa chọn.",
  "content": "<p>Nội dung đã được biên tập và đối chiếu nguồn.</p>",
  "status": "draft",
  "doctors": [{"doctor_id": 123, "rank_order": 1}]
}
```

Nếu chưa có ID, dùng `doctor_id: 0`, `name`, `specialty_text` và ít nhất `city` hoặc `facility_name`. Có thể thêm `title_text`, `address`, `phone`, `website`, `image_url`, `facility_slug`. Thông tin nhận diện phải thực, không dữ liệu mẫu.
Hồ sơ mới chỉ là hồ sơ tối thiểu, chưa xác thực; nội dung chuyên sâu tiếp tục qua API viết bác sĩ. API không nhận rating/verified từ AI, không ghi đè hồ sơ được liên kết bằng ID.
Tên trùng nhau được đối chiếu chuyên khoa + thành phố + nơi công tác; nếu có nhiều bản trùng thì cần ID chính xác. Nhập đầy đủ lỗi sẽ rollback cả bài lẫn hồ sơ mới.

## API tiện ích

Giữ header `X-Medical-Api-Key` hiện tại. Không cache các endpoint này.

- GET `/api/medical/toplists-needing-facilities.php`: hàng đợi chưa có liên kết. Mặc định vẫn chỉ cơ sở để tiện ích cũ không nhận nhầm bài bác sĩ; `entity_type=doctor` lấy bài bác sĩ.
- GET `/api/medical/toplists-needing-members.php`: hàng đợi cả hai loại; filter `entity_type=doctor` hoặc `facility`.
- Mỗi item trả `entity_type`, `prompt_type: "toplist"`, `prompt`, `receive_endpoint: "/api/medical/toplist-members-update.php"`.
- POST `/api/medical/toplist-members-update.php`: nhận cả hai loại.
- POST `/api/medical/toplist-doctors-update.php`: chỉ nhận loại bác sĩ.
- POST `/api/medical/toplist-facilities-update.php`: URL cũ vẫn hoạt động và nhận được cả hai payload.

Tiện ích cần cập nhật bộ đọc JSON `doctors` trước khi chuyển sang hàng đợi chung/bác sĩ. Giữ `entity_type` của item; dùng prompt trả về, giữ đúng khóa `doctors` hoặc `facilities`, không ép mọi kết quả về cơ sở. Prompt bác sĩ tự thay template cơ sở cũ nếu template chưa có placeholder loại bài. Các placeholder mới: `{{entity_type}}`, `{{entity_label}}`, `{{member_key}}`, `{{output_template}}`.

POST liên kết vào bài đang có:

```json
{
  "toplist_id": 456,
  "entity_type": "doctor",
  "doctors": [
    {"doctor_id": 123, "rank_order": 1},
    {"doctor_id": 124, "rank_order": 2}
  ]
}
```

Đây là **thay thế toàn bộ thứ hạng**, không append. Không nhận danh sách rỗng hoặc đổi loại bài qua API (đổi trong admin trước).
Batch `{ "items": [...] }` tối đa 25 bài; từng bài có transaction riêng.
HTTP 200: tất cả thành công; 207: batch có thành công và lỗi; 422: không cập nhật do validation; 500: lỗi hệ thống.
Response giữ `updated_toplist_ids`, `updated_count`, `created_facility_ids/count`; thêm `created_doctor_ids/count`, `results` với `member_ids`, `errors` object có `index`, `toplist_id`, `status`, `message`.

## Hiển thị và bản dịch

Toplist bác sĩ mở hồ sơ `/bac-si/{slug}`, hiển thị chuyên khoa/nơi công tác và chỉ dùng đánh giá của bác sĩ. Trang danh sách, trang chủ và tìm kiếm dùng số lượng/ảnh đúng loại.
Bản tiếng Anh giữ loại bài và chỉ nối bác sĩ có bản tiếng Anh đã xuất bản, không nối hồ sơ tiếng Việt vào bài tiếng Anh.

Migration cộng thêm, không đổi bài/liên kết đang có:

```sh
php scripts/migrate_toplist_doctors.php
php tests/toplist_doctors_test.php --mysql-temporary
```

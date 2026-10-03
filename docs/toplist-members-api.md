# Toplists: cơ sở y tế và bác sĩ

Mỗi bài có `medical_toplists.entity_type`: `facility` (bài cũ), `doctor` hoặc `mixed` (cơ sở và bác sĩ cùng bài).
Hai bảng `medical_toplist_facilities` và `medical_toplist_doctors` dùng chung thứ tự `rank_order` của cả bài. ID phải đi kèm loại: cơ sở #1 và bác sĩ #1 là hai hồ sơ khác nhau. Liên kết chỉ nhận hồ sơ đã xuất bản, cùng ngôn ngữ với bài.

## Admin

- `medical_toplist_edit.php`: bài mới mặc định cơ sở & bác sĩ. Chuyển bộ lọc tìm kiếm không xoá hồ sơ đã chọn. Thêm loại thứ hai vào bài thuần tự chuyển sang `mixed`; kéo thả xếp hạng chung. Lưu bài và cả hai bảng liên kết trong cùng transaction.
- `medical_toplists.php`: có mẫu JSON / prompt hỗn hợp, bác sĩ, cơ sở. Nhập tiêu đề giữ loại bài cho hàng đợi AI.
- JSON có `doctors` nhận diện `doctor`; có `members` hoặc cả `facilities` và `doctors` nhận diện `mixed`. Không dùng `members` đồng thời với hai mảng không rỗng.

Ví dụ một bài có cả hai loại (thay ID bằng hồ sơ thực):

```json
{
  "title": "Cơ sở và bác sĩ nha khoa tại Đà Nẵng đáng tham khảo",
  "entity_type": "mixed",
  "status": "draft",
  "members": [
    {"type": "facility", "facility_id": 123, "rank_order": 1},
    {"type": "doctor", "doctor_id": 123, "rank_order": 2}
  ]
}
```

Cũng nhận `facilities:[...]` và `doctors:[...]` cùng object, với rank_order dùng chung để đan xen thứ hạng. Trùng một hồ sơ cùng type chỉ giữ lần đầu sau khi sắp xếp; ID cùng số khác type không bị gộp.

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
- GET `/api/medical/toplists-needing-members.php`: hàng đợi cả ba loại; filter `entity_type=doctor`, `facility`, `mixed`. Bài hỗn hợp chỉ vào hàng đợi khi chưa có liên kết ở cả hai bảng, không coi bài cố ý chỉ có một loại là thiếu liên kết.
- Mỗi item trả `entity_type`, `prompt_type: "toplist"`, `prompt`, `receive_endpoint: "/api/medical/toplist-members-update.php"`.
- POST `/api/medical/toplist-members-update.php`: nhận cả ba loại, bao gồm `members` hỗn hợp.
- POST `/api/medical/toplist-doctors-update.php`: chỉ nhận loại bác sĩ.
- POST `/api/medical/toplist-facilities-update.php`: URL cũ vẫn hoạt động và nhận được cả hai payload.

Tiện ích cần hỗ trợ `doctors` và `members` (type-qualified ID) trước khi chuyển sang hàng đợi chung. Giữ `entity_type` và dùng đúng prompt/receive_endpoint trả về; không ép kết quả về cơ sở. Prompt bác sĩ/hỗn hợp tự thay template cơ sở cũ nếu template chưa có placeholder loại bài. Các placeholder: `{{entity_type}}`, `{{entity_label}}`, `{{member_key}}`, `{{output_template}}`.

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
Response giữ `updated_toplist_ids`, `updated_count`, `created_facility_ids/count`, `created_doctor_ids/count`. `results.members` luôn là `[{"type":"facility","id":123},...]` theo thứ hạng. `member_ids` giữ mảng số cho bài thuần; bài mixed trả mảng type/id. `errors` có `index`, `toplist_id`, `status`, `message`.

## Hiển thị và bản dịch

Toplist hỗn hợp hiển thị cùng danh sách với nhãn rõ từng loại; cơ sở mở `/co-so-y-te/{slug}`, bác sĩ mở `/bac-si/{slug}`. Không gán đánh giá cơ sở cho bác sĩ. Schema ItemList dùng MedicalClinic/Physician đúng từng mục. Trang danh sách, trang chủ và tìm kiếm cộng số lượng hai loại cho bài mixed.
Bản tiếng Anh giữ loại bài, liên kết cả hai loại có bản tiếng Anh đã xuất bản và giữ thứ hạng chung; không nối hồ sơ tiếng Việt vào bài tiếng Anh.

Migration cộng thêm, không đổi bài/liên kết đang có:

```sh
php scripts/migrate_toplist_doctors.php
php tests/toplist_doctors_test.php --mysql-temporary
```

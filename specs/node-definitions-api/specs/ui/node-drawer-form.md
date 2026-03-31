# Node Drawer Form (節點設定抽屜表單)

## 描述
點選節點後開啟的 Drawer，以統一的 DynamicNodeForm 元件根據 form_fields schema 動態渲染表單欄位。完全取代目前的 EmailForm、WaitForm、DefaultForm，移除所有硬編碼 Form 元件。

## 行為
- 點選節點時，根據 node_definition_id 找到對應的 form_fields schema
- 根據 form_fields 中每個欄位的 type 渲染對應的 Ant Design 元件
- 支援 depends_on 條件顯示
- 儲存時將表單值寫入 NodeDTO.params

## 類型映射
| form_field.type   | Ant Design 元件        |
|-------------------|------------------------|
| text              | Input                  |
| number            | InputNumber            |
| select            | Select                 |
| textarea          | Input.TextArea         |
| template_editor   | BlockNoteEditor        |
| switch            | Switch                 |
| date              | DatePicker             |
| json              | Input.TextArea + JSON 驗證 |

## 關鍵屬性
- 欄位定義來源：NodeDefinition.form_fields
- 欄位排序：依 sort 屬性排列
- 條件顯示：depends_on 控制欄位可見性

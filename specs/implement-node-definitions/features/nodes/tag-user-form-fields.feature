@ignore @command
Feature: TagUserNode 表單欄位更新

  TagUserNode 的 tags 欄位從 select 改為 tags_input，
  允許用戶自由輸入純字串標籤，不需預定義選項。

  Rule: 後置（狀態）- tags 欄位型別應為 tags_input

    Example: TagUserNode form_fields 定義
      Given 系統已註冊 TagUserNode
      When 檢查 TagUserNode 的 form_fields
      Then tags 欄位的 type 應為 "tags_input"
      And tags 欄位的 name 應為 "tags"
      And tags 欄位的 label 應為 "標籤"
      And tags 欄位的 required 應為 true
      And tags 欄位不應有 options 屬性

  Rule: 後置（狀態）- action 欄位應保持不變

    Example: action 欄位仍為 select
      Given 系統已註冊 TagUserNode
      When 檢查 TagUserNode 的 form_fields
      Then action 欄位的 type 應為 "select"
      And action 欄位的 options 應包含 "add" 和 "remove"

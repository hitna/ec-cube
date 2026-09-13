/* カスタマイズ用Javascript */

$(function() {
    // ヘッダーのカートポップアップは、ポップアップ外（画面の他の部分）をクリックしたら閉じる
    // ヘッダーのカート部分はAjaxで差し替えられるため、documentへの委譲で登録する
    $(document).on('click', function(event) {
        if ($(event.target).closest('.ec-cartNaviWrap').length) {
            // カートアイコン／ポップアップ内のクリックは対象外（開閉はアイコン側の処理に任せる）
            return;
        }
        $('.ec-cartNaviIsset, .ec-cartNaviNull').removeClass('is-active');
    });
});

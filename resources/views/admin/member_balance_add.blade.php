@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">調整餘額</h4>
                            <form class="forms-sample" id="form">

                                <div class="form-group">
                                    <label>*會員</label>
                                    <div class="position-relative">
                                        <input type="text" id="member_search_input"
                                               class="form-control"
                                               placeholder="輸入帳號或暱稱搜尋" autocomplete="off">
                                        <input type="hidden" name="member_id" id="member_id_hidden" class="required">
                                        <div id="member_dropdown"
                                             class="list-group position-absolute w-100"
                                             style="z-index:9999;display:none;max-height:220px;overflow-y:auto;box-shadow:0 4px 8px rgba(0,0,0,.15);">
                                        </div>
                                    </div>
                                    <small id="member_balance_hint" class="form-text text-muted"></small>
                                </div>

                                <div class="form-group">
                                    <label>*類型</label>
                                    <select class="form-control required" name="type">
                                        <option value="1">增加</option>
                                        <option value="2">減少</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>*金額</label>
                                    <input type="number" class="form-control required" name="amount" step="0.01" min="0.01" placeholder="請輸入金額">
                                </div>

                                <div class="form-group">
                                    <label>備注</label>
                                    <input type="text" class="form-control" name="remark" placeholder="選填">
                                </div>

                                <button type="button" onclick="commit()" class="btn btn-sm btn-gradient-primary btn-icon-text">
                                    <i class="mdi mdi-file-check btn-icon-prepend"></i>
                                    提交
                                </button>
                                <button type="button" onclick="cancel()" class="btn btn-sm btn-gradient-warning btn-icon-text">
                                    <i class="mdi mdi-reload btn-icon-prepend"></i>
                                    取消
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var searchTimer = null;

        $('#member_search_input').on('input', function () {
            var keyword = $(this).val().trim();
            clearTimeout(searchTimer);
            $('#member_id_hidden').val('');
            $('#member_balance_hint').text('');
            if (keyword.length === 0) {
                $('#member_dropdown').hide();
                return;
            }
            searchTimer = setTimeout(function () {
                $.get('/admin/member/search', {keyword: keyword}, function (data) {
                    var $dropdown = $('#member_dropdown').empty();
                    if (data.length === 0) {
                        $dropdown.append('<div class="list-group-item text-muted small">無符合結果</div>');
                    } else {
                        $.each(data, function (i, m) {
                            $('<a href="#" class="list-group-item list-group-item-action py-1 small"></a>')
                                .text(m.account + ' (' + m.nickname + ') — 存款：' + parseFloat(m.balance).toFixed(2))
                                .on('click', function (e) {
                                    e.preventDefault();
                                    $('#member_search_input').val(m.account + ' (' + m.nickname + ')');
                                    $('#member_id_hidden').val(m.id);
                                    $('#member_balance_hint').text('目前存款：' + parseFloat(m.balance).toFixed(2));
                                    $dropdown.hide();
                                })
                                .appendTo($dropdown);
                        });
                    }
                    $dropdown.show();
                });
            }, 300);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#member_search_input, #member_dropdown').length) {
                $('#member_dropdown').hide();
            }
        });

        function commit() {
            if (!$('#member_id_hidden').val()) {
                layer.msg('請先選擇會員');
                return false;
            }
            if (!checkForm()) return false;
            var data = $('#form').serializeObject();
            myRequest('/admin/member/balance/add', 'post', data, function (res) {
                layer.msg(res.msg);
                if (res.code == 200) {
                    setTimeout(function () {
                        parent.location.reload();
                    }, 1500);
                }
            });
        }

        function cancel() {
            parent.location.reload();
        }
    </script>
@endsection

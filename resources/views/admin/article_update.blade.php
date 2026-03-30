@extends('base.base')
@section('base')
    <div class="main-panel">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title">修改文章</h4>
                            <form class="forms-sample" id="form">

                                <div class="form-group">
                                    <label>*標題</label>
                                    <input type="text" class="form-control required" name="title" value="{{ $article->title }}" placeholder="請輸入文章標題">
                                </div>

                                <div class="form-group">
                                    <label>封面圖片</label>
                                    <input type="file" class="file-upload-default img-file" data-path="article">
                                    <input type="hidden" name="image" class="image-path" value="{{ $article->image }}">
                                    <div class="input-group col-xs-12">
                                        <input type="text" class="form-control file-upload-info" disabled="" value="{{ $article->image }}">
                                        <span class="input-group-append">
                                            <button class="file-upload-browse btn btn-gradient-primary" onclick="upload($(this))" type="button">上傳</button>
                                        </span>
                                    </div>
                                    <div class="img-yl" @if($article->image) style="display:block;" @endif>
                                        @if($article->image)
                                            <img src="{{ $article->image }}">
                                        @endif
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>*類型</label>
                                    <select class="form-control" name="type">
                                        <option value="1" @if($article->type == 1) selected @endif>普通文章</option>
                                        <option value="4" @if($article->type == 4) selected @endif>玩股網</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>文章內容</label>
                                    <div id="editor"></div>
                                    <input type="hidden" name="content" id="content">
                                </div>

                                <div class="form-group">
                                    <label>上架狀態</label>
                                    <div class="form-check">
                                        <label class="form-check-label">
                                            <input type="checkbox" class="form-check-input" name="is_active" value="1" @if($article->is_active) checked @endif>
                                            上架
                                            <i class="input-helper"></i>
                                        </label>
                                    </div>
                                </div>

                                <button type="button" onclick="commit({{ $article->id }})" class="btn btn-sm btn-gradient-primary btn-icon-text">
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
        var editor = new wangEditor('editor');
        editor.config.uploadImgUrl = '/admin/wangeditor/upload';
        editor.config.uploadImgFileName = 'wangEditorH5File';
        editor.create();
        editor.txt.$txt.html({!! json_encode($article->content) !!});

        function commit(id) {
            if (!checkForm()) return false;
            $('#content').val(editor.txt.$txt.html());
            var data = $('#form').serializeObject();
            data.is_active = $('input[name=is_active]').is(':checked') ? 1 : 0;
            myRequest('/admin/article/update/' + id, 'post', data, function (res) {
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

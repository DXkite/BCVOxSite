<?php 
// SEO 优化 蜘蛛专用样式
// if (is_spider()) {
//     View::theme('spider');
// }

if (conf('Uninstall')) {
    // 未安装
    Page::default('Install::start');
} else {
    // 主页
    Page::visit('/', 'Main->main')->use('index')->id('main_page')->noCache();
    // 待开发的页面
    Page::visit('/{pagename}', 'Develop->main')->with('pagename', '/^(notes|question|test|books|about)$/')->use('developing')->id('develop_page')->noCache();
    // 文章列表
    Page::visit('/article/{page}?', 'article\View->list')->with('page', 'int')->id('article_list')->noCache();
    // 文章分类
    Page::visit('/article/category:{name}/{page}?', 'article\View->listCategory')->with('name', 'string')->with('page', 'int')->id('article_category_list')->noCache();
    // 文章标签
    Page::visit('/article/tag:{name}/{page}?', 'article\View->listTag')->with('name', 'string')->with('page', 'int')->id('article_tag_list')->noCache();
    // 用户主页
    Page::visit('/user:{userid}/{username}?', 'user\View->main')->with('userid', 'int')->with('username', 'string')->id('user_view')->override()->noCache();
    // 查看文章
    Page::visit('/article:{aid}/{name}?', 'article\View->article')->with('aid', 'int')->with('name', 'string')->id('article_view')->override()->noCache();
    // 头像Alias
    Page::visit('/avatar/{name}?', 'user\View::avatar')->with('name', 'string')->id('user_avatar')->age(10000)->close();
    // 邮箱验证
    Page::visit('/mail-verify:{uid}.{token}', 'verify@user/email_verify')->with('uid', 'int')->with('token', 'string')->id('mail_verify')->noCache();
    // 模板文件
    Page::visit('/theme/{path}', 'View::file')->with('path', '/^(.+)$/')->id('theme')->override()->age(10000)->close();
    // 上传的文件
    Page::visit('/upload:{id}/{name}?', 'Resource::main')->with('id', 'int')->with('name', 'string')->id('upload')->override();
    // 用户页面
    Page::auto('/user', '/user')->id('user')->noCache();
    // 管理界面导向
    Page::auto('/admin', '/admin')->id('admin')->noCache();
    // 管理界面导向
    Page::visit('/admin/{entrance}', 'admin\Index->entrance')->with('entrance', 'string')->id('admin_entrance')->noCache();
    // 测试页面
    Page::auto('/test', '/test')->id('test')->noCache();
    // 验证码
    Page::visit('/verify_code', 'Image->verifyImage')->raw()->type('png')->id('verify_code');
    // 404 页面
    Page::visit('/404_page.html', 'Page::error404')->use(404)->status(404)->id('404_page');
    // 找不到页面时
    Page::default('Page::error404')->use(404)->status(404);
}

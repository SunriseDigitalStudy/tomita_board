{extends file='default/base.tpl'}
{block title append} コメントページです{/block}
{block main_contents}
    <p>表示確認も兼ねて、試験的に何かを書いておく。<br>
    現時点ではEntryテーブルには何もレコードが無いので<br>
    ここにはデータが出力されない　はず</p>
    <p>レコードが追加されれば、以下に出力される</p>
    <table class="table">
        <tr class="success">
            <th>ID</th>
            <th>スレッドID</th>
            <th>アカウントID</th>
            <th>記事内容</th>
            <th>記事投稿日時</th>
            <th>スレ立て日時</th>
        </tr>
       {foreach $entry_list as $record}
        <tr>
             <td>{$record->getId()}</td>
             <td>{$record->getThread_id()}</td>
             <td>{$record->get_Account_id()}</td>
             <td>{$record->get_Body()}</td>
             <td>{$record->get_Updated_at()}</td>
             <td>{$record->get_Created_at()}</td>
        </tr>
        {/foreach}
    </table>
{/block}
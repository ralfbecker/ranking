<!-- BEGIN header -->
<form method="POST" action="{action_url}">
{hidden_vars}
<table border="0" align="center">
   <tr class="th">
    <td colspan="2">&nbsp;<b>{title}</b></td>
   </tr>
<!-- END header -->

<!-- BEGIN body -->
   <tr class="row_on">
    <td colspan="2">&nbsp;</td>
   </tr>
   <tr class="row_off">
    <td>{lang_VFS_directory_for_the_pdf_files_(excluding_the_year)}</td>
    <td><input name="newsettings[vfs_pdf_dir]" value="{value_vfs_pdf_dir}"></td>
   </tr>
   <tr class="row_on">
    <td>{lang_Charset_of_the_ranking_database_(only_if_different_from_eGW)}:</td>
    <td><input size="8" name="newsettings[ranking_db_charset]" value="{value_ranking_db_charset}"></td>
   </tr>
<!-- END body -->

<!-- BEGIN footer -->
  <tr class="th">
    <td colspan="2">
&nbsp;
    </td>
  </tr>
  <tr>
    <td colspan="2" align="center">
      <input type="submit" name="submit" value="{lang_submit}">
      <input type="submit" name="cancel" value="{lang_cancel}">
    </td>
  </tr>
</table>
</form>
<!-- END footer -->

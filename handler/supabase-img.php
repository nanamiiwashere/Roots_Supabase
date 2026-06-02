<?php
function supabaseImageUrl($fileName) {
  if (empty($fileName)) return null;
  if (str_starts_with($fileName, 'http')) return $fileName;
  return SUPABASE_URL . '/storage/v1/object/public/' . SUPABASE_BUCKET . '/' . $fileName;
}
?>
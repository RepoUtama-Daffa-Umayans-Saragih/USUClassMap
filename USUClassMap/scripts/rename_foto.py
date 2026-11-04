import os
import re
from pathlib import Path

def batch_rename_360_photos(target_directory: str):
    """
    Melakukan batch rename file foto 360 derajat dari format kamera:
    IMG_YYYYMMDD_HHMMSS_00_XXX.jpg -> XXX.jpg
    """
    folder_path = Path(target_directory)

    # Validasi keberadaan direktori
    if not folder_path.exists() or not folder_path.is_dir():
        print(f"❌ Error: Direktori '{target_directory}' tidak ditemukan.")
        return

    # Pola regex untuk mengekstrak angka nomor urut terakhir (misal: 424 dari ..._00_424.jpg)
    pattern = re.compile(r'_(\d+)\.jpe?g$', re.IGNORECASE)

    renamed_count = 0
    skipped_count = 0
    error_count = 0

    print(f"🔍 Memindai file di: {folder_path.resolve()}\n" + "-" * 50)

    # Iterasi semua file di folder target
    for file_path in folder_path.iterdir():
        if file_path.is_file() and file_path.suffix.lower() in ['.jpg', '.jpeg']:
            original_name = file_path.name

            # Cocokkan nama file dengan pola regex
            match = pattern.search(original_name)
            if not match:
                print(f"⏭️  Dilewati (tidak sesuai pola): {original_name}")
                skipped_count += 1
                continue

            extracted_number = match.group(1)
            extension = file_path.suffix.lower()
            new_name = f"{extracted_number}{extension}"
            new_file_path = folder_path / new_name

            # Safety Check 1: Lewati jika nama file sudah sesuai format tujuan
            if original_name == new_name:
                skipped_count += 1
                continue

            # Safety Check 2: Mencegah penimpaan (overwrite) file yang sudah ada
            if new_file_path.exists():
                print(f"⚠️  Peringatan: File tujuan '{new_name}' sudah ada! Melewati '{original_name}'...")
                skipped_count += 1
                continue

            # Proses Rename dengan penanganan error
            try:
                file_path.rename(new_file_path)
                print(f"✅ Berhasil: '{original_name}' ➔ '{new_name}'")
                renamed_count += 1
            except Exception as e:
                print(f"❌ Gagal me-rename '{original_name}': {e}")
                error_count += 1

    print("-" * 50)
    print("📊 LAPORAN BATCH RENAME:")
    print(f"   • Berhasil di-rename : {renamed_count} file")
    print(f"   • Dilewati            : {skipped_count} file")
    print(f"   • Gagal (Error)       : {error_count} file")
    print("=" * 50)


if __name__ == "__main__":
    # Direktori target foto 360
    TARGET_DIR = r"D:\Documents\TUBES WEB SEMANTIK\FOTO 360\FIB"
    
    batch_rename_360_photos(TARGET_DIR)
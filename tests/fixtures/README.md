`simplexls-books.xls` is an upstream binary XLS parser fixture, not school data.

Source: https://github.com/shuchkin/simplexls/blob/0aad1dd0c8703c8fcc3234eda9d22d08e25b6579/examples/books.xls

MIT license: see `../../includes/vendor/simplexls/LICENSE.md`.

`ujian-soal-roundtrip.xlsx` contains two synthetic UH questions (PG and essay).
It was generated from the application template, opened and saved with openpyxl
to exercise an independent XLSX round trip. `ujian-soal-legacy.xls` contains the
same questions in binary XLS format, generated with xlwt. Neither contains
school data. Test execution uses the existing PHP readers and needs neither
Python package.

`ujian-soal-bobot.xlsx` is a synthetic XLSX round trip containing one blank
weight and one whole decimal weight written as `3,00`. It verifies that the
teacher import uses defaults and accepts Excel whole-number formatting.

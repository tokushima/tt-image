# tt-image

PHPのImagick拡張をラップした画像処理ライブラリ。

## インストール

### ImageMagick本体

```sh
brew install pkg-config imagemagick
```

### PHP Imagick拡張

```sh
pecl install imagick
```

`pcre2.h` が見つからないエラーが出る場合は、シンボリックリンクを張る:

```sh
ln -s $(brew --prefix pcre2)/include/pcre2.h $(php-config --include-dir)/ext/pcre/pcre2.h
```

peclでインストールできない場合はソースからビルド:

```sh
git clone https://github.com/Imagick/imagick
cd imagick
phpize && ./configure
make && make install
```

## VS Code (PHP Intelephense) 対応

拡張機能の設定 → `Intelephense: Stubs` に `imagick` を追加する。

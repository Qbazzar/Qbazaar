# Fonts

The Google Fonts files of Poppins, Noto Kufi Arabic, Montserrat, Story Script, Dancing Script, Acme and Geist Mono (latin subset, or the Arabic blocks only for Noto Kufi Arabic), loaded by `index.ts`. Each family is licensed under the [SIL Open Font License 1.1](https://openfontlicense.org); the copyright notice is inside each file.

To add a weight, download the subset file Google Fonts serves for it (`fonts.googleapis.com/css2?family=...`), name it `family-subset-weight.woff2` and add it to the family's `src` in `index.ts`.

Noto Kufi Arabic is the variable font (wght 100-900) from the notofonts/arabic release NotoKufiArabic-v2.110, subset with `pyftsubset` to U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF, space, no-break space, U+200C-200F, U+2010-2011, U+060C, U+061B and U+061F, all layout features kept (`--layout-features=*`), no Latin letters or ASCII digits so those fall through to Poppins. Its licence is `OFL-NotoKufiArabic.txt`.

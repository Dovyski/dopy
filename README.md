# Dopy

Dopy is a simple tool to transcribe documentation and signatures of C++ function into their equivalent in Python. The main goal of the tool is to help developers port their C++ libraries into Python.

## Getting started

### 1. Prerequisites

You need [PHP](https://php.net) available in the command line to run Dopy.

### 2. Installing

Clone Dopy's repository in a folder of your choice, e.g. `/opt/local/`:

```
cd /opt/local && git clone https://github.com/Dovyski/dopy.git dopy
```

## Usage

Dopy expects that all functions in the C++ file to be transcribed have a `/** ... */` comment block whose documentation uses `\tag` marks, as illustrated below:

```cpp

/**
 Here goes the description of the function.
 You might have descriptions with multiple lines as well.

 \param theParam information about the param.
 \return true if something.

 \sa init()
*/
bool update(int theParam);
```

Assuming the function above is in the C++ file `mylib.h`, you can transcribe it into Python using the following command:

```
/opt/local/dopy/dopy --input=mylib.h --output=mylib.py
```

Dopy will print to stdout any warnings and a message informing if everything went ok. All transcribed content will be output in the file informed via `--output`, i.e. `mylib.py` in this case.

## Limitations

Dopy is not a C++ to Python transpiler or compiler. It simply aims to speed up the manual process of porting C++ code into Python by automating some tedious steps, e.g. port of documentation and function signatures. Dopy also expects the input C++ code to be formatted in a certain way, which might not be the one you are using. Finally the transcribed Python code follows [Google's Python Style Guide](https://google.github.io/styleguide/pyguide.html).

## License

Dopy is licensed under the terms of the [MIT](https://choosealicense.com/licenses/mit/) Open Source
license and is available for free.

## Changelog

See all changes in the [CHANGELOG](CHANGELOG.md) file.

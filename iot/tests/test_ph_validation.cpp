#include "../running/ph_validation.h"

#include <limits>
#include <iostream>

static int failures = 0;

void expectTrue(const char* name, bool actual) {
  if (!actual) {
    std::cout << "FAIL: " << name << "\n";
    failures++;
  }
}

void expectFalse(const char* name, bool actual) {
  expectTrue(name, !actual);
}

int main() {
  expectTrue("pH 0 valid", isPhValueValid(0.0f));
  expectTrue("pH 2.10 valid", isPhValueValid(2.10f));
  expectTrue("pH 7.44 valid", isPhValueValid(7.44f));
  expectTrue("pH 14 valid", isPhValueValid(14.0f));
  expectFalse("pH -0.01 invalid", isPhValueValid(-0.01f));
  expectFalse("pH 14.01 invalid", isPhValueValid(14.01f));
  expectFalse("pH 19.22 invalid", isPhValueValid(19.22f));
  expectFalse("pH NaN invalid", isPhValueValid(std::numeric_limits<float>::quiet_NaN()));
  return failures;
}

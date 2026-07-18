import XCTest
@testable import CRM369iOS

final class DomainURLNormalizerTests: XCTestCase {
    func testNormalizesProductionDomainToHTTPS() {
        XCTAssertEqual(
            DomainURLNormalizer.normalize(" crm.company.com/path?query=1 ")?.absoluteString,
            "https://crm.company.com"
        )
    }

    func testUsesHTTPForLocalHerdDomain() {
        XCTAssertEqual(
            DomainURLNormalizer.normalize("crm369.test")?.absoluteString,
            "http://crm369.test"
        )
    }

    func testRejectsUnsupportedSchemeAndCredentials() {
        XCTAssertNil(DomainURLNormalizer.normalize("javascript:alert(1)"))
        XCTAssertNil(DomainURLNormalizer.normalize("https://user:secret@crm.company.com"))
    }
}

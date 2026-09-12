import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.recognition.matcher import best_match
from src.recognition.similarity import cosine_similarity


def test_cosine_similarity_identical_vectors():
    assert cosine_similarity([1.0, 0.0], [1.0, 0.0]) == 1.0


def test_cosine_similarity_orthogonal_vectors():
    assert cosine_similarity([1.0, 0.0], [0.0, 1.0]) == 0.0


def test_cosine_similarity_zero_vector_is_safe():
    assert cosine_similarity([0.0, 0.0], [1.0, 0.0]) == 0.0


def test_best_match_picks_closest_embedding():
    probe = [1.0, 0.0]
    catalog = [
        {"student_id": 1, "matricula": "A001", "embedding": [0.0, 1.0]},  # ortogonal
        {"student_id": 2, "matricula": "A002", "embedding": [1.0, 0.0]},  # idéntico
    ]

    match = best_match(probe, catalog)

    assert match["student_id"] == 2
    assert match["confidence"] == 1.0


def test_best_match_empty_catalog_returns_none():
    assert best_match([1.0, 0.0], []) is None
